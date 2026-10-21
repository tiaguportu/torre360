<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Fatura;
use App\Models\ItemFatura;
use App\Models\Matricula;
use App\Models\TransacaoBancaria;
use App\Models\User;
use App\Support\Formatacao;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Informe anual de mensalidades pagas: o que cada família pagou à escola no ano-calendário, mês a mês, e a parte que
 * costuma ser aceita como despesa com instrução no imposto de renda.
 *
 * Regime de caixa: conta o dinheiro que ENTROU de 1º/1 a 31/12 (data da transação bancária), não o vencimento da
 * fatura. Pagamento parcial entra pelo valor recebido. A parte "dedutível" é uma ESTIMATIVA: cada pagamento é repartido
 * entre os itens da fatura na proporção do valor líquido deles, e os itens cuja descrição contém uma palavra de
 * `config('financeiro.informe_anual.palavras_nao_dedutiveis')` ficam de fora. Não substitui o recibo nem a nota fiscal.
 */
class InformeAnualMensalidadesService
{
    /**
     * @param  Collection<int, int>|array<int, int>|null  $matriculaIds  restringe às matrículas informadas
     * @return Collection<int, array<string, mixed>> um item por matrícula que teve pagamento no ano, por nome do aluno
     */
    public function gerar(int $ano, Collection|array|null $matriculaIds = null, ?int $turmaId = null, ?string $busca = null): Collection
    {
        $ids = $matriculaIds !== null ? collect($matriculaIds)->values()->all() : null;
        $busca = $busca !== null ? trim($busca) : null;

        $transacoes = TransacaoBancaria::query()
            ->where('tipo', 'entrada')
            ->whereNotNull('fatura_id')
            ->whereDate('data_transacao', '>=', sprintf('%04d-01-01', $ano))
            ->whereDate('data_transacao', '<=', sprintf('%04d-12-31', $ano))
            ->whereHas('fatura.contrato', function (Builder $contrato) use ($ids, $turmaId, $busca): void {
                $contrato->whereNotNull('matricula_id');

                if ($ids !== null) {
                    $contrato->whereIn('matricula_id', $ids);
                }

                if ($turmaId !== null) {
                    $contrato->whereHas('matricula', fn (Builder $m) => $m->where('turma_id', $turmaId));
                }

                if ($busca) {
                    $contrato->whereHas('matricula.pessoa', fn (Builder $p) => $p->where('nome', 'like', '%'.addcslashes($busca, '%_\\').'%'));
                }
            })
            ->with([
                'fatura.itens',
                'fatura.contrato.matricula.pessoa',
                'fatura.contrato.matricula.turma.serie.curso.unidade.instituicaoEnsino',
                'fatura.contrato.responsaveisFinanceiros.pessoa',
            ])
            ->orderBy('data_transacao')
            ->orderBy('id')
            ->get();

        return $transacoes
            ->groupBy(fn (TransacaoBancaria $t): int => (int) $t->fatura->contrato->matricula_id)
            ->map(fn (Collection $grupo): array => $this->montarLinha($grupo, $ano))
            ->sortBy(fn (array $linha): string => Str::ascii(mb_strtolower((string) $linha['aluno']['nome'])))
            ->values();
    }

    /**
     * Matrículas que o usuário enxerga como família: do próprio ou de dependente, ou em que consta como responsável
     * financeiro do contrato.
     *
     * @return Builder<Matricula>
     */
    public function matriculasDaFamilia(User $user): Builder
    {
        $pessoas = $user->pessoasAcessiveis()->pluck('id');

        return Matricula::query()->where(function (Builder $q) use ($pessoas): void {
            $q->whereIn('pessoa_id', $pessoas)
                ->orWhereHas('contrato.responsaveisFinanceiros', fn (Builder $r) => $r->whereIn('pessoa_id', $pessoas));
        });
    }

    /**
     * A equipe com permissão vê qualquer aluno; a família, só os seus.
     */
    public function podeVer(User $user, Matricula $matricula): bool
    {
        if ($user->can('GerarInformeAnual:Fatura')) {
            return true;
        }

        return $this->matriculasDaFamilia($user)->whereKey($matricula->getKey())->exists();
    }

    /**
     * @param  Collection<int, TransacaoBancaria>  $transacoes  todas de uma mesma matrícula
     * @return array<string, mixed>
     */
    private function montarLinha(Collection $transacoes, int $ano): array
    {
        /** @var TransacaoBancaria $primeira */
        $primeira = $transacoes->first();
        $contrato = $primeira->fatura->contrato;
        $matricula = $contrato->matricula;
        $unidade = $matricula->turma?->serie?->curso?->unidade;
        $instituicao = $unidade?->instituicaoEnsino;

        $porMes = array_fill(1, 12, 0.0);
        $pagamentos = [];
        $total = $dedutivel = 0.0;

        foreach ($transacoes as $transacao) {
            $valor = (float) $transacao->valor;
            $parteDedutivel = round($valor * $this->proporcaoDedutivel($transacao->fatura), 2);
            $data = Carbon::parse($transacao->data_transacao);

            $porMes[(int) $data->format('n')] += $valor;
            $total += $valor;
            $dedutivel += $parteDedutivel;

            $pagamentos[] = [
                'data' => $data,
                'fatura_id' => $transacao->fatura_id,
                'descricao' => $transacao->fatura->itens->first()?->descricao ?? "Fatura #{$transacao->fatura_id}",
                'valor' => $valor,
                'dedutivel' => $parteDedutivel,
            ];
        }

        $total = round($total, 2);
        $responsaveis = $contrato->responsaveisFinanceiros->map(fn ($r): array => [
            'nome' => (string) $r->pessoa?->nome,
            'cpf' => Formatacao::cpf($r->pessoa?->cpf),
            'percentual' => (float) $r->percentual,
            'valor' => round($total * ((float) $r->percentual / 100), 2),
        ])->values()->all();

        return [
            'matricula_id' => $matricula->id,
            'ano' => $ano,
            'aluno' => ['nome' => (string) $matricula->pessoa?->nome, 'cpf' => Formatacao::cpf($matricula->pessoa?->cpf)],
            'turma' => $matricula->turma?->nome,
            'serie' => $matricula->turma?->serie?->nome,
            'escola' => [
                'nome' => $instituicao?->nome ?? $unidade?->nome ?? 'Escola',
                'unidade' => $unidade?->nome,
                'cnpj' => Formatacao::cnpj($unidade?->cnpj ?: $instituicao?->cnpj),
            ],
            'responsaveis' => $responsaveis,
            'por_mes' => array_map(fn (float $v): float => round($v, 2), $porMes),
            'pagamentos' => $pagamentos,
            'total_pago' => $total,
            'total_dedutivel' => round($dedutivel, 2),
            'total_nao_dedutivel' => round($total - $dedutivel, 2),
        ];
    }

    /**
     * Fração do pagamento da fatura que corresponde a itens considerados dedutíveis (0 a 1).
     */
    private function proporcaoDedutivel(Fatura $fatura): float
    {
        $totalGeral = $totalDedutivel = 0.0;

        foreach ($fatura->itens as $item) {
            $liquido = $this->valorLiquido($item);
            $totalGeral += $liquido;

            if (! $this->descricaoNaoDedutivel((string) $item->descricao)) {
                $totalDedutivel += $liquido;
            }
        }

        return $totalGeral > 0 ? min(1.0, max(0.0, $totalDedutivel / $totalGeral)) : 1.0;
    }

    private function valorLiquido(ItemFatura $item): float
    {
        $bruto = (float) $item->valor_unitario * (float) $item->quantidade;
        $desconto = $item->tipo_desconto === 'absoluto'
            ? (float) $item->desconto
            : $bruto * ((float) $item->desconto / 100);

        return max(0.0, $bruto - $desconto);
    }

    private function descricaoNaoDedutivel(string $descricao): bool
    {
        $texto = Str::lower(Str::ascii($descricao));

        foreach ((array) config('financeiro.informe_anual.palavras_nao_dedutiveis', []) as $palavra) {
            if ($palavra !== '' && str_contains($texto, Str::lower(Str::ascii((string) $palavra)))) {
                return true;
            }
        }

        return false;
    }
}
