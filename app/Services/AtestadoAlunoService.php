<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StatusAtestadoAluno;
use App\Models\AtestadoAluno;
use App\Models\FrequenciaEscolar;
use App\Models\Matricula;
use App\Models\User;
use App\Support\ArquivoPrivado;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Atestado do aluno: justificativa e abono das faltas do período.
 *
 * Justificar e abonar são coisas diferentes. O atestado sempre **justifica** as faltas do período (`justificada = true`,
 * com o vínculo ao atestado): a família não recebe o aviso diário e a falta entra como justificada no Conselho Tutelar,
 * mas continua contando na frequência. Só se o atestado foi registrado como **abono** (`abona_faltas`) as faltas também
 * ficam `abonada = true`, e aí deixam de contar contra a frequência (boletim, Portal, Censo, risco de evasão).
 *
 * Só entram faltas ainda injustificadas: a justificativa (e o abono) lançada à mão por outra pessoa não é sobrescrita
 * nem desfeita. Ao cancelar ou corrigir o período, só as faltas vinculadas ao atestado voltam a injustificadas, e ficam
 * justificadas pelos demais atestados vigentes do aluno, se houver. A falta lançada depois do atestado é justificada
 * pelo `FrequenciaFaltaObserver`. O motivo gravado na falta nunca traz a observação do atestado (dado de saúde).
 */
class AtestadoAlunoService
{
    /**
     * Teto do período, para uma data digitada errada (ano de 2062) não abonar anos de faltas.
     */
    public const PERIODO_MAXIMO_DIAS = 180;

    /**
     * O que a descrição do log de `FrequenciaEscolar` lê (aluno, turma e disciplina da aula): carregar junto evita uma
     * consulta por falta abonada.
     */
    private const RELACOES_DO_LOG = ['matricula.pessoa', 'cronogramaAula.turma', 'cronogramaAula.disciplina'];

    /**
     * Texto gravado em `motivo_justificativa` das faltas justificadas pelo atestado.
     */
    public static function motivoDaJustificativa(AtestadoAluno $atestado): string
    {
        return 'Atestado médico ('.$atestado->periodo().')';
    }

    /**
     * O atestado vigente que cobre o dia (o mais antigo, se houver mais de um).
     */
    public function atestadoVigenteEm(int $matriculaId, CarbonInterface $data): ?AtestadoAluno
    {
        return AtestadoAluno::query()
            ->where('matricula_id', $matriculaId)
            ->vigentes()
            ->cobrindo($data)
            ->orderBy('id')
            ->first();
    }

    /**
     * Guarda o comprovante em área privada, cria o atestado e justifica as faltas já lançadas no período. Com `$abonaFaltas`
     * o atestado fica registrado como abono e as faltas também deixam de contar na frequência.
     *
     * @throws \DomainException período inválido ou comprovante que não é PDF/foto
     */
    public function registrar(
        Matricula $matricula,
        CarbonInterface $inicio,
        CarbonInterface $fim,
        UploadedFile $comprovante,
        ?string $observacao = null,
        ?User $por = null,
        bool $abonaFaltas = false,
    ): AtestadoAluno {
        $this->validarPeriodo($inicio, $fim);

        $repetido = AtestadoAluno::query()
            ->where('matricula_id', $matricula->id)
            ->vigentes()
            ->whereDate('data_inicio', $inicio->toDateString())
            ->whereDate('data_fim', $fim->toDateString())
            ->exists();

        if ($repetido) {
            throw new \DomainException('Já existe um atestado vigente deste aluno para o mesmo período.');
        }

        // O sufixo evita que dois atestados do mesmo aluno, no mesmo segundo, gravem no mesmo arquivo.
        $caminho = ArquivoPrivado::guardarDocumento($comprovante, "atestados_aluno/{$matricula->id}", 'atestado-'.now()->format('YmdHis').'-'.Str::lower(Str::random(6)));

        try {
            return DB::transaction(function () use ($matricula, $inicio, $fim, $caminho, $observacao, $por, $abonaFaltas): AtestadoAluno {
                $atestado = AtestadoAluno::create([
                    'matricula_id' => $matricula->id,
                    'data_inicio' => $inicio->toDateString(),
                    'data_fim' => $fim->toDateString(),
                    'arquivo_path' => $caminho,
                    'observacao' => filled($observacao) ? trim((string) $observacao) : null,
                    'abona_faltas' => $abonaFaltas,
                    'status' => StatusAtestadoAluno::Vigente,
                    'registrado_por_user_id' => $por?->id,
                ]);

                $justificadas = $this->justificarFaltas($atestado);

                $this->registrarLog($atestado, $por, "Atestado registrado ({$atestado->periodo()}): {$justificadas} falta(s) justificada(s){$this->sufixoDoAbono($atestado)}.", [
                    'faltas_justificadas' => $justificadas,
                    'abona_faltas' => $abonaFaltas,
                ]);

                return $atestado;
            });
        } catch (Throwable $e) {
            Storage::disk('local')->delete($caminho);

            throw $e;
        }
    }

    /**
     * Corrige o período de um atestado vigente: as faltas que ficaram de fora voltam a injustificadas e as novas do
     * período são justificadas. `$abonaFaltas` (quando informado) muda o abono, e a mudança vale para todas as faltas do
     * atestado: marcar abona as que só estavam justificadas; desmarcar devolve as abonadas a "só justificadas".
     *
     * @param  ?bool  $abonaFaltas  nulo mantém o abono como está
     * @return int faltas justificadas a mais pelo novo período
     *
     * @throws \DomainException
     */
    public function corrigirPeriodo(AtestadoAluno $atestado, CarbonInterface $inicio, CarbonInterface $fim, ?User $por = null, ?bool $abonaFaltas = null): int
    {
        if (! $atestado->estaVigente()) {
            throw new \DomainException('Atestado cancelado não pode ser corrigido.');
        }

        $this->validarPeriodo($inicio, $fim);

        return DB::transaction(function () use ($atestado, $inicio, $fim, $por, $abonaFaltas): int {
            $atestado->update([
                'data_inicio' => $inicio->toDateString(),
                'data_fim' => $fim->toDateString(),
                'abona_faltas' => $abonaFaltas ?? $atestado->abona_faltas,
            ]);

            $devolvidas = $this->liberarFaltas($atestado, somenteForaDoPeriodo: true);
            $justificadas = $this->justificarFaltas($atestado);
            $this->reaplicarPelosDemais($atestado);

            // O texto da justificativa leva o período e o abono pode ter mudado: acerta as faltas deste atestado (uma a
            // uma, para o histórico de alterações da falta registrar a mudança).
            $atestado->frequencias()->with(self::RELACOES_DO_LOG)->get()->each(fn (FrequenciaEscolar $falta) => $falta->update([
                'abonada' => $atestado->abona_faltas,
                'motivo_justificativa' => self::motivoDaJustificativa($atestado),
            ]));

            $this->registrarLog($atestado, $por, "Atestado corrigido para {$atestado->periodo()}: {$justificadas} falta(s) justificada(s), {$devolvidas} devolvida(s){$this->sufixoDoAbono($atestado)}.", [
                'faltas_justificadas' => $justificadas,
                'faltas_devolvidas' => $devolvidas,
                'abona_faltas' => $atestado->abona_faltas,
            ]);

            return $justificadas;
        });
    }

    /**
     * Cancela o atestado (lançado por engano, falso...): as faltas que ele abonou voltam a injustificadas. O comprovante
     * continua guardado, para o histórico.
     *
     * @return int faltas que voltaram a injustificadas
     *
     * @throws \DomainException
     */
    public function cancelar(AtestadoAluno $atestado, string $motivo, ?User $por = null): int
    {
        if (! $atestado->estaVigente()) {
            throw new \DomainException('Este atestado já está cancelado.');
        }

        $motivo = trim($motivo);

        if ($motivo === '') {
            throw new \DomainException('Informe o motivo do cancelamento.');
        }

        return DB::transaction(function () use ($atestado, $motivo, $por): int {
            $atestado->update([
                'status' => StatusAtestadoAluno::Cancelado,
                'motivo_cancelamento' => $motivo,
                'cancelado_em' => now(),
                'cancelado_por_user_id' => $por?->id,
            ]);

            $devolvidas = $this->liberarFaltas($atestado);
            $this->reaplicarPelosDemais($atestado);

            $this->registrarLog($atestado, $por, "Atestado cancelado: {$devolvidas} falta(s) voltaram a injustificadas e a contar na frequência.", ['faltas_devolvidas' => $devolvidas]);

            return $devolvidas;
        });
    }

    /**
     * Justifica as faltas do aluno dentro do período que ainda estão injustificadas e, se o atestado é abono, abona.
     *
     * @return int quantidade de faltas justificadas
     */
    public function justificarFaltas(AtestadoAluno $atestado): int
    {
        if (! $atestado->estaVigente()) {
            return 0;
        }

        $motivo = self::motivoDaJustificativa($atestado);

        // get() antes do update: o filtro `justificada = false` muda ao justificar e quebraria uma paginação em lotes.
        $faltas = FrequenciaEscolar::query()
            ->with(self::RELACOES_DO_LOG)
            ->where('matricula_id', $atestado->matricula_id)
            ->where('situacao', 'ausente')
            ->where('justificada', false)
            ->whereHas('cronogramaAula', fn ($aulas) => $aulas
                ->whereDate('data', '>=', $atestado->data_inicio->toDateString())
                ->whereDate('data', '<=', $atestado->data_fim->toDateString()))
            ->get();

        foreach ($faltas as $falta) {
            $falta->update([
                'justificada' => true,
                'abonada' => $atestado->abona_faltas,
                'motivo_justificativa' => $motivo,
                'atestado_aluno_id' => $atestado->id,
            ]);
        }

        return $faltas->count();
    }

    /**
     * Devolve a injustificadas (e a contar na frequência) as faltas justificadas por este atestado.
     *
     * @param  bool  $somenteForaDoPeriodo  na correção de período, só as que deixaram de ser cobertas
     * @return int quantidade de faltas devolvidas
     */
    private function liberarFaltas(AtestadoAluno $atestado, bool $somenteForaDoPeriodo = false): int
    {
        $consulta = $atestado->frequencias()->with(self::RELACOES_DO_LOG);

        if ($somenteForaDoPeriodo) {
            $consulta->whereHas('cronogramaAula', fn ($aulas) => $aulas
                ->where(fn ($fora) => $fora
                    ->whereDate('data', '<', $atestado->data_inicio->toDateString())
                    ->orWhereDate('data', '>', $atestado->data_fim->toDateString())));
        }

        $faltas = $consulta->get();

        foreach ($faltas as $falta) {
            $falta->update([
                'justificada' => false,
                'abonada' => false,
                'motivo_justificativa' => null,
                'atestado_aluno_id' => null,
            ]);
        }

        return $faltas->count();
    }

    /**
     * Uma falta liberada por um atestado pode estar coberta por outro atestado vigente do mesmo aluno.
     */
    private function reaplicarPelosDemais(AtestadoAluno $atestado): void
    {
        AtestadoAluno::query()
            ->where('matricula_id', $atestado->matricula_id)
            ->where('id', '!=', $atestado->id)
            ->vigentes()
            ->get()
            ->each(fn (AtestadoAluno $outro) => $this->justificarFaltas($outro));
    }

    /**
     * Trecho do log: ", abonadas" quando o atestado é abono; vazio quando só justifica.
     */
    private function sufixoDoAbono(AtestadoAluno $atestado): string
    {
        return $atestado->abona_faltas ? ', todas abonadas (não contam na frequência)' : ', sem abono (contam na frequência)';
    }

    /**
     * @throws \DomainException
     */
    private function validarPeriodo(CarbonInterface $inicio, CarbonInterface $fim): void
    {
        if ($fim->toDateString() < $inicio->toDateString()) {
            throw new \DomainException('A data final não pode ser anterior à data inicial.');
        }

        // Só as datas contam (a hora que vier junto não pode virar um dia a mais).
        $dias = Carbon::parse($inicio->toDateString())->diffInDays(Carbon::parse($fim->toDateString())) + 1;

        if ($dias > self::PERIODO_MAXIMO_DIAS) {
            throw new \DomainException('O período do atestado não pode passar de '.self::PERIODO_MAXIMO_DIAS.' dias. Confira as datas.');
        }
    }

    /**
     * @param  array<string, mixed>  $propriedades
     */
    private function registrarLog(AtestadoAluno $atestado, ?User $por, string $mensagem, array $propriedades = []): void
    {
        $log = activity('secretaria')
            ->performedOn($atestado)
            ->withProperties(['matricula_id' => $atestado->matricula_id] + $propriedades);

        if ($por) {
            $log->causedBy($por);
        }

        $log->log($mensagem);
    }
}
