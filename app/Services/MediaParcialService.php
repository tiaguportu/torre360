<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SituacaoFinal;
use App\Models\Avaliacao;
use App\Models\Nota;
use App\Models\PeriodoLetivo;
use Illuminate\Support\Collection;

/**
 * Média parcial de cada aluno na grade de lançamento de notas, enquanto o professor digita.
 *
 * É a mesma conta do boletim ({@see BoletimService::calcularMediaFinal()}): média ponderada das avaliações da disciplina
 * na etapa, com as categorias de substituição. A diferença é a origem da nota da avaliação aberta na grade: ela vem do
 * que está digitado (ainda não salvo), não do banco. A faixa de cor vem da mesma regra do fechamento do ciclo
 * ({@see FechamentoCicloService::classificarSituacao()}): aprovação e recuperação mínima do período letivo.
 */
class MediaParcialService
{
    public function __construct(
        private readonly BoletimService $boletim,
        private readonly FechamentoCicloService $fechamento,
    ) {}

    /**
     * Carrega uma vez o que o cálculo de todos os alunos da grade precisa (duas consultas, não uma por linha).
     *
     * `notas` não inclui as da própria avaliação aberta: valem as digitadas na grade.
     *
     * @return array{avaliacao: Avaliacao, avaliacoes: Collection<int, Avaliacao>, notas: Collection<int, Collection<int, Nota>>, periodo: PeriodoLetivo}
     */
    public function contexto(Avaliacao $avaliacao): array
    {
        $avaliacoes = Avaliacao::query()
            ->where('turma_id', $avaliacao->turma_id)
            ->where('disciplina_id', $avaliacao->disciplina_id)
            ->where('etapa_avaliativa_id', $avaliacao->etapa_avaliativa_id)
            ->with('categoria.substituidas')
            ->get();

        $notas = Nota::query()
            ->whereIn('avaliacao_id', $avaliacoes->pluck('id')->reject(fn ($id): bool => (int) $id === (int) $avaliacao->id)->values())
            ->whereNotNull('valor')
            ->get()
            ->groupBy('matricula_id')
            ->map(fn (Collection $doAluno): Collection => $doAluno->keyBy('avaliacao_id'));

        $avaliacao->loadMissing('etapaAvaliativa.periodoLetivo', 'turma.periodoLetivo');

        return [
            'avaliacao' => $avaliacao,
            'avaliacoes' => $avaliacoes,
            'notas' => $notas,
            // Sem período letivo cadastrado valem os padrões do fechamento (aprovação 7,0 e recuperação mínima 5,0).
            'periodo' => $avaliacao->etapaAvaliativa?->periodoLetivo ?? $avaliacao->turma?->periodoLetivo ?? new PeriodoLetivo,
        ];
    }

    /**
     * Média parcial do aluno com a nota digitada no lugar da salva. Valor vazio, inválido ou acima da nota máxima não
     * entra (a gravação recusa esse valor), então a média sai só com as outras avaliações.
     *
     * @param  array{avaliacao: Avaliacao, avaliacoes: Collection<int, Avaliacao>, notas: Collection<int, Collection<int, Nota>>, periodo: PeriodoLetivo}  $contexto
     */
    public function media(array $contexto, int $matriculaId, mixed $valorDigitado): ?float
    {
        /** @var Avaliacao $avaliacao */
        $avaliacao = $contexto['avaliacao'];

        // Cópia: o `put` abaixo não pode alterar o contexto compartilhado com as outras linhas.
        $notas = collect($contexto['notas']->get($matriculaId, collect())->all());
        $valor = $this->valorValido($valorDigitado, $avaliacao);

        if ($valor !== null) {
            $notas->put($avaliacao->id, (object) ['valor' => $valor]);
        }

        return $this->boletim->calcularMediaFinal((int) $avaliacao->disciplina_id, $contexto['avaliacoes'], $notas);
    }

    /**
     * Aprovado (verde), Recuperação (âmbar) ou Reprovado (vermelho), comparando a média arredondada a uma casa, como o
     * boletim a exibe.
     *
     * @param  array{avaliacao: Avaliacao, avaliacoes: Collection<int, Avaliacao>, notas: Collection<int, Collection<int, Nota>>, periodo: PeriodoLetivo}  $contexto
     */
    public function situacao(array $contexto, ?float $media): ?SituacaoFinal
    {
        return $media === null ? null : $this->fechamento->classificarSituacao(round($media, 1), $contexto['periodo']);
    }

    /**
     * "Verde a partir de 7,0 (aprovação); âmbar a partir de 5,0 (recuperação); vermelho abaixo."
     *
     * @param  array{avaliacao: Avaliacao, avaliacoes: Collection<int, Avaliacao>, notas: Collection<int, Collection<int, Nota>>, periodo: PeriodoLetivo}  $contexto
     */
    public function legenda(array $contexto): string
    {
        $aprovacao = $this->formatar((float) ($contexto['periodo']->nota_aprovacao ?? 7.0));
        $recuperacao = $this->formatar((float) ($contexto['periodo']->nota_recuperacao_minima ?? 5.0));

        return "Média parcial da etapa na disciplina, com as notas digitadas (antes de salvar). Verde: a partir de {$aprovacao} (aprovação). Âmbar: a partir de {$recuperacao} (recuperação). Vermelho: abaixo disso.";
    }

    public function formatar(float $valor): string
    {
        return number_format(round($valor, 1), 1, ',', '.');
    }

    private function valorValido(mixed $valor, Avaliacao $avaliacao): ?float
    {
        if ($valor === null || $valor === '' || ! is_scalar($valor)) {
            return null;
        }

        $texto = str_replace(',', '.', trim((string) $valor));

        if (! is_numeric($texto)) {
            return null;
        }

        $numero = (float) $texto;

        return $numero < 0 || $numero > (float) $avaliacao->nota_maxima ? null : $numero;
    }
}
