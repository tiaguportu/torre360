<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StatusAvaliacaoDocente;
use App\Exceptions\AvaliacaoDocenteException;
use App\Models\AvaliacaoDocente;
use App\Models\AvaliacaoDocenteCriterio;
use App\Models\CicloAvaliacaoDocente;
use App\Models\Funcionario;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ciclo de vida de uma avaliação de desempenho docente: iniciar a observação (já com uma linha por critério do
 * instrumento), consolidar a pontuação, finalizar e reabrir.
 *
 * Pontuação: média das notas ponderada pelo peso de cada critério, expressa em % da nota máxima da escala. Assim
 * ela é comparável entre instrumentos de escalas diferentes e não muda se a escala for alterada depois.
 */
class AvaliacaoDocenteService
{
    /**
     * @param  array{ciclo_id: int|string, funcionario_id: int|string, data_observacao: mixed, turma_id?: int|string|null}  $dados
     *
     * @throws AvaliacaoDocenteException
     */
    public function iniciar(array $dados, User $avaliador): AvaliacaoDocente
    {
        $ciclo = CicloAvaliacaoDocente::query()->with('instrumento.criterios')->findOrFail($dados['ciclo_id']);
        $funcionario = Funcionario::query()->findOrFail($dados['funcionario_id']);
        $data = Carbon::parse($dados['data_observacao'])->startOfDay();

        if (! $ciclo->ativo) {
            throw new AvaliacaoDocenteException('Este ciclo de avaliação está encerrado.');
        }

        if (! $ciclo->aceitaObservacaoEm($data)) {
            throw new AvaliacaoDocenteException(sprintf(
                'A data da observação precisa estar dentro do ciclo (%s a %s).',
                $ciclo->data_inicio->format('d/m/Y'),
                $ciclo->data_fim->format('d/m/Y'),
            ));
        }

        if ($data->isFuture()) {
            throw new AvaliacaoDocenteException('A data da observação não pode ser futura.');
        }

        if ($funcionario->data_desligamento && $funcionario->data_desligamento->copy()->startOfDay()->lt($data)) {
            throw new AvaliacaoDocenteException('Este funcionário já estava desligado na data da observação.');
        }

        if ($ciclo->instrumento->criterios->isEmpty()) {
            throw new AvaliacaoDocenteException('O instrumento deste ciclo não tem critérios cadastrados.');
        }

        if ($avaliador->pessoas()->where('pessoa.id', $funcionario->pessoa_id)->exists()) {
            throw new AvaliacaoDocenteException('Não é possível avaliar o próprio desempenho.');
        }

        $jaExiste = AvaliacaoDocente::query()
            ->where('ciclo_id', $ciclo->id)
            ->where('funcionario_id', $funcionario->id)
            ->where('avaliador_user_id', $avaliador->id)
            ->whereDate('data_observacao', $data->toDateString())
            ->exists();

        if ($jaExiste) {
            throw new AvaliacaoDocenteException('Você já registrou uma observação deste professor neste ciclo para esta data.');
        }

        return DB::transaction(function () use ($ciclo, $funcionario, $avaliador, $data, $dados): AvaliacaoDocente {
            $avaliacao = AvaliacaoDocente::create([
                'ciclo_id' => $ciclo->id,
                'funcionario_id' => $funcionario->id,
                'avaliador_user_id' => $avaliador->id,
                'turma_id' => $dados['turma_id'] ?? null,
                'data_observacao' => $data->toDateString(),
                'status' => StatusAvaliacaoDocente::Rascunho,
            ]);

            foreach ($ciclo->instrumento->criterios as $criterio) {
                AvaliacaoDocenteCriterio::create([
                    'avaliacao_docente_id' => $avaliacao->id,
                    'criterio_id' => $criterio->id,
                ]);
            }

            return $avaliacao;
        });
    }

    /**
     * @throws AvaliacaoDocenteException
     */
    public function finalizar(AvaliacaoDocente $avaliacao, User $por): AvaliacaoDocente
    {
        return DB::transaction(function () use ($avaliacao, $por): AvaliacaoDocente {
            $avaliacao = AvaliacaoDocente::query()->lockForUpdate()->with(['notas.criterio', 'ciclo.instrumento'])->findOrFail($avaliacao->id);

            if ($avaliacao->estaFinalizada()) {
                throw new AvaliacaoDocenteException('Esta avaliação já foi finalizada.');
            }

            $escala = (int) $avaliacao->ciclo->instrumento->escala_maxima;

            $semNota = $avaliacao->notas->filter(fn (AvaliacaoDocenteCriterio $n): bool => $n->nota === null);
            if ($semNota->isNotEmpty()) {
                throw new AvaliacaoDocenteException('Faltam notas nos critérios: '.$semNota->map(fn (AvaliacaoDocenteCriterio $n): string => (string) $n->criterio?->nome)->implode(', ').'.');
            }

            $foraDaEscala = $avaliacao->notas->filter(fn (AvaliacaoDocenteCriterio $n): bool => $n->nota < 1 || $n->nota > $escala);
            if ($foraDaEscala->isNotEmpty()) {
                throw new AvaliacaoDocenteException("As notas precisam estar entre 1 e {$escala}.");
            }

            $percentual = $this->calcularPercentual($avaliacao->notas, $escala);

            // O log automático do model descreveria só "atualizada"; registramos o evento com quem finalizou.
            $avaliacao->disableLogging();
            $avaliacao->update([
                'status' => StatusAvaliacaoDocente::Finalizada,
                'pontuacao_percentual' => $percentual,
                'finalizada_em' => now(),
            ]);
            $avaliacao->enableLogging();

            activity('avaliacao_docente')
                ->performedOn($avaliacao)
                ->causedBy($por)
                ->withProperties(['pontuacao_percentual' => $percentual])
                ->log('Avaliação docente finalizada.');

            return $avaliacao->refresh();
        });
    }

    /**
     * Devolve a avaliação a rascunho para correção. A pontuação é zerada e só volta ao finalizar de novo.
     *
     * @throws AvaliacaoDocenteException
     */
    public function reabrir(AvaliacaoDocente $avaliacao, User $por): AvaliacaoDocente
    {
        return DB::transaction(function () use ($avaliacao, $por): AvaliacaoDocente {
            $avaliacao = AvaliacaoDocente::query()->lockForUpdate()->findOrFail($avaliacao->id);

            if (! $avaliacao->estaFinalizada()) {
                throw new AvaliacaoDocenteException('Só uma avaliação finalizada pode ser reaberta.');
            }

            $avaliacao->disableLogging();
            $avaliacao->update([
                'status' => StatusAvaliacaoDocente::Rascunho,
                'pontuacao_percentual' => null,
                'finalizada_em' => null,
            ]);
            $avaliacao->enableLogging();

            activity('avaliacao_docente')
                ->performedOn($avaliacao)
                ->causedBy($por)
                ->log('Avaliação docente reaberta para correção.');

            return $avaliacao->refresh();
        });
    }

    /**
     * Média ponderada das notas, em % da nota máxima da escala.
     *
     * @param  Collection<int, AvaliacaoDocenteCriterio>  $notas
     */
    public function calcularPercentual(Collection $notas, int $escalaMaxima): float
    {
        $obtido = 0.0;
        $possivel = 0.0;

        foreach ($notas as $nota) {
            $peso = (float) ($nota->criterio?->peso ?? 1);
            $obtido += (int) $nota->nota * $peso;
            $possivel += $escalaMaxima * $peso;
        }

        return $possivel > 0 ? round($obtido / $possivel * 100, 2) : 0.0;
    }
}
