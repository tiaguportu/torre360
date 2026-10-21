<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StatusAcaoDesenvolvimento;
use App\Enums\StatusAvaliacaoDocente;
use App\Enums\StatusPlanoDesenvolvimento;
use App\Exceptions\DesenvolvimentoDocenteException;
use App\Models\AcaoDesenvolvimentoDocente;
use App\Models\AutoavaliacaoDocente;
use App\Models\AutoavaliacaoNota;
use App\Models\AvaliacaoDocente;
use App\Models\AvaliacaoDocenteCriterio;
use App\Models\CicloAvaliacaoDocente;
use App\Models\Funcionario;
use App\Models\PlanoDesenvolvimentoDocente;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Plano de desenvolvimento individual (PDI) do professor, criado a partir de uma avaliação finalizada.
 *
 * Fluxo: a coordenação cria o plano (já com ações sugeridas para os critérios mais fracos), ajusta e ATIVA; o
 * professor só passa a enxergá-lo ativo, dá ciência e registra o andamento das ações; a coordenação conclui quando
 * não há ação em aberto. Cancelar uma ação é decisão da coordenação, nunca do próprio professor.
 */
class DesenvolvimentoDocenteService
{
    /** Critério com nota abaixo de 70% da escala vira ação sugerida. */
    public const LIMITE_SUGESTAO = 0.7;

    /** Prazo padrão das ações sugeridas, em dias. */
    public const PRAZO_PADRAO_DIAS = 90;

    /**
     * Comparação, critério a critério, entre a autoavaliação enviada do professor e a média das observações já
     * finalizadas da coordenação no mesmo ciclo. A diferença positiva significa que o professor se vê melhor do que a
     * coordenação o viu.
     *
     * @return array{escala: int, avaliacoes: int, auto_pontuacao: ?float, observada_pontuacao: ?float, linhas: list<array{criterio_id: int, nome: string, peso: float, auto: ?int, observada: ?float, diferenca: ?float}>}
     */
    public function comparativo(Funcionario $funcionario, CicloAvaliacaoDocente $ciclo): array
    {
        $ciclo->loadMissing('instrumento.criterios');

        $auto = AutoavaliacaoDocente::query()
            ->with('notas')
            ->where('ciclo_id', $ciclo->id)
            ->where('funcionario_id', $funcionario->id)
            ->where('status', 'enviada')
            ->first();

        $avaliacoes = AvaliacaoDocente::query()
            ->with('notas')
            ->where('ciclo_id', $ciclo->id)
            ->where('funcionario_id', $funcionario->id)
            ->where('status', StatusAvaliacaoDocente::Finalizada->value)
            ->get();

        $linhas = [];

        foreach ($ciclo->instrumento->criterios as $criterio) {
            $notaAuto = $auto?->notas->first(fn (AutoavaliacaoNota $n): bool => (int) $n->criterio_id === (int) $criterio->id)?->nota;

            $notasObservadas = $avaliacoes
                ->flatMap(fn (AvaliacaoDocente $a) => $a->notas)
                ->filter(fn (AvaliacaoDocenteCriterio $n): bool => (int) $n->criterio_id === (int) $criterio->id && $n->nota !== null)
                ->pluck('nota');

            $observada = $notasObservadas->isNotEmpty() ? round((float) $notasObservadas->avg(), 2) : null;

            $linhas[] = [
                'criterio_id' => $criterio->id,
                'nome' => $criterio->nome,
                'peso' => (float) $criterio->peso,
                'auto' => $notaAuto,
                'observada' => $observada,
                'diferenca' => $notaAuto !== null && $observada !== null ? round($notaAuto - $observada, 2) : null,
            ];
        }

        $pontuacoes = $avaliacoes->pluck('pontuacao_percentual')->filter(fn ($p): bool => $p !== null);

        return [
            'escala' => (int) $ciclo->instrumento->escala_maxima,
            'avaliacoes' => $avaliacoes->count(),
            'auto_pontuacao' => $auto?->pontuacao_percentual !== null ? (float) $auto->pontuacao_percentual : null,
            'observada_pontuacao' => $pontuacoes->isNotEmpty() ? round((float) $pontuacoes->avg(), 2) : null,
            'linhas' => $linhas,
        ];
    }

    /**
     * Cria o plano em rascunho, com uma ação sugerida para cada critério abaixo de {@see self::LIMITE_SUGESTAO}.
     *
     * @throws DesenvolvimentoDocenteException
     */
    public function criarPlano(AvaliacaoDocente $avaliacao, User $por): PlanoDesenvolvimentoDocente
    {
        return DB::transaction(function () use ($avaliacao, $por): PlanoDesenvolvimentoDocente {
            $avaliacao = AvaliacaoDocente::query()->lockForUpdate()->with(['notas.criterio', 'ciclo.instrumento'])->findOrFail($avaliacao->id);

            if (! $avaliacao->estaFinalizada()) {
                throw new DesenvolvimentoDocenteException('O plano de desenvolvimento nasce de uma avaliação finalizada.');
            }

            if (PlanoDesenvolvimentoDocente::query()->where('avaliacao_docente_id', $avaliacao->id)->exists()) {
                throw new DesenvolvimentoDocenteException('Esta avaliação já tem um plano de desenvolvimento.');
            }

            $plano = PlanoDesenvolvimentoDocente::create([
                'avaliacao_docente_id' => $avaliacao->id,
                'funcionario_id' => $avaliacao->funcionario_id,
                'status' => StatusPlanoDesenvolvimento::Rascunho,
                'criado_por_user_id' => $por->id,
            ]);

            $escala = max(1, (int) $avaliacao->ciclo->instrumento->escala_maxima);
            $ordem = 1;

            foreach ($avaliacao->notas->sortBy('nota') as $nota) {
                if ($nota->nota === null || $nota->nota / $escala >= self::LIMITE_SUGESTAO) {
                    continue;
                }

                AcaoDesenvolvimentoDocente::create([
                    'plano_id' => $plano->id,
                    'criterio_id' => $nota->criterio_id,
                    'descricao' => sprintf(
                        'Trabalhar o critério “%s” (nota %d de %d): combinar com a coordenação uma prática observável, o apoio necessário e como o avanço será acompanhado.',
                        $nota->criterio?->nome ?? 'sem nome',
                        $nota->nota,
                        $escala,
                    ),
                    'prazo' => today()->addDays(self::PRAZO_PADRAO_DIAS)->toDateString(),
                    'status' => StatusAcaoDesenvolvimento::Pendente,
                    'ordem' => $ordem++,
                ]);
            }

            return $plano;
        });
    }

    /**
     * Libera o plano para o professor.
     *
     * @throws DesenvolvimentoDocenteException
     */
    public function ativar(PlanoDesenvolvimentoDocente $plano, User $por): PlanoDesenvolvimentoDocente
    {
        return DB::transaction(function () use ($plano): PlanoDesenvolvimentoDocente {
            $plano = PlanoDesenvolvimentoDocente::query()->lockForUpdate()->with('acoes')->findOrFail($plano->id);

            if ($plano->status !== StatusPlanoDesenvolvimento::Rascunho) {
                throw new DesenvolvimentoDocenteException('Só um plano em rascunho pode ser ativado.');
            }

            $acoes = $plano->acoes->filter(fn (AcaoDesenvolvimentoDocente $a): bool => $a->status !== StatusAcaoDesenvolvimento::Cancelada);

            if ($acoes->isEmpty()) {
                throw new DesenvolvimentoDocenteException('Inclua ao menos uma ação antes de ativar o plano.');
            }

            if ($acoes->contains(fn (AcaoDesenvolvimentoDocente $a): bool => $a->estaAtrasada())) {
                throw new DesenvolvimentoDocenteException('Há ação com prazo já vencido. Ajuste os prazos antes de ativar o plano.');
            }

            $plano->update([
                'status' => StatusPlanoDesenvolvimento::Ativo,
                'ativado_em' => now(),
                'data_revisao' => $plano->data_revisao ?? $acoes->max('prazo'),
            ]);

            return $plano->refresh();
        });
    }

    /**
     * O professor confirma que leu o plano. Não é concordância: só registra que tomou ciência.
     *
     * @throws DesenvolvimentoDocenteException
     */
    public function darCiencia(PlanoDesenvolvimentoDocente $plano, User $por): PlanoDesenvolvimentoDocente
    {
        $plano->loadMissing('funcionario');

        if (! $plano->pertenceA($por)) {
            throw new DesenvolvimentoDocenteException('Só o professor do plano dá ciência.');
        }

        if ($plano->status !== StatusPlanoDesenvolvimento::Ativo) {
            throw new DesenvolvimentoDocenteException('O plano ainda não foi ativado pela coordenação.');
        }

        if ($plano->ciencia_em === null) {
            $plano->update(['ciencia_em' => now()]);
        }

        return $plano->refresh();
    }

    /**
     * Registra o andamento de uma ação. O professor do plano marca "em andamento" e "concluída" (com o resultado); a
     * coordenação também pode, e só ela cancela, nunca nas ações do próprio plano.
     *
     * @throws DesenvolvimentoDocenteException
     */
    public function atualizarAcao(AcaoDesenvolvimentoDocente $acao, StatusAcaoDesenvolvimento $novoStatus, ?string $resultado, User $por): AcaoDesenvolvimentoDocente
    {
        return DB::transaction(function () use ($acao, $novoStatus, $resultado, $por): AcaoDesenvolvimentoDocente {
            $acao = AcaoDesenvolvimentoDocente::query()->lockForUpdate()->with('plano.funcionario')->findOrFail($acao->id);
            $plano = $acao->plano;

            if ($plano->status !== StatusPlanoDesenvolvimento::Ativo) {
                throw new DesenvolvimentoDocenteException('O andamento só é registrado em plano ativo.');
            }

            if (! $acao->status->emAberto()) {
                throw new DesenvolvimentoDocenteException('Esta ação já foi encerrada.');
            }

            $ehOProprio = $plano->pertenceA($por);
            $coordenacao = $por->can('Update:PlanoDesenvolvimentoDocente');
            $resultado = $resultado !== null ? trim($resultado) : null;

            if (! $ehOProprio && ! $coordenacao) {
                throw new DesenvolvimentoDocenteException('Você não pode registrar o andamento desta ação.');
            }

            switch ($novoStatus) {
                case StatusAcaoDesenvolvimento::EmAndamento:
                    if ($acao->status !== StatusAcaoDesenvolvimento::Pendente) {
                        throw new DesenvolvimentoDocenteException('A ação já está em andamento.');
                    }
                    $acao->update(['status' => StatusAcaoDesenvolvimento::EmAndamento]);
                    break;

                case StatusAcaoDesenvolvimento::Concluida:
                    if (mb_strlen((string) $resultado) < 10) {
                        throw new DesenvolvimentoDocenteException('Descreva o resultado da ação (pelo menos 10 caracteres).');
                    }
                    $acao->update([
                        'status' => StatusAcaoDesenvolvimento::Concluida,
                        'resultado' => $resultado,
                        'concluida_em' => today()->toDateString(),
                    ]);
                    break;

                case StatusAcaoDesenvolvimento::Cancelada:
                    if ($ehOProprio || ! $coordenacao) {
                        throw new DesenvolvimentoDocenteException('Só a coordenação cancela uma ação, e nunca nas ações do próprio plano.');
                    }
                    if (mb_strlen((string) $resultado) < 10) {
                        throw new DesenvolvimentoDocenteException('Explique por que a ação foi cancelada (pelo menos 10 caracteres).');
                    }
                    $acao->update(['status' => StatusAcaoDesenvolvimento::Cancelada, 'resultado' => $resultado]);
                    break;

                default:
                    throw new DesenvolvimentoDocenteException('Situação inválida para o andamento da ação.');
            }

            return $acao->refresh();
        });
    }

    /**
     * Conclui o plano quando não há mais ação em aberto.
     *
     * @throws DesenvolvimentoDocenteException
     */
    public function concluir(PlanoDesenvolvimentoDocente $plano, User $por): PlanoDesenvolvimentoDocente
    {
        return DB::transaction(function () use ($plano): PlanoDesenvolvimentoDocente {
            $plano = PlanoDesenvolvimentoDocente::query()->lockForUpdate()->with('acoes')->findOrFail($plano->id);

            if ($plano->status !== StatusPlanoDesenvolvimento::Ativo) {
                throw new DesenvolvimentoDocenteException('Só um plano ativo pode ser concluído.');
            }

            $emAberto = $plano->acoes->filter(fn (AcaoDesenvolvimentoDocente $a): bool => $a->status->emAberto())->count();

            if ($emAberto > 0) {
                throw new DesenvolvimentoDocenteException("Ainda há {$emAberto} ação(ões) em aberto. Conclua ou cancele cada uma antes.");
            }

            $plano->update(['status' => StatusPlanoDesenvolvimento::Concluido, 'concluido_em' => now()]);

            return $plano->refresh();
        });
    }
}
