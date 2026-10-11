<?php

namespace App\Services;

use App\Models\PlanoConta;
use App\Models\TransacaoBancaria;
use Illuminate\Support\Collection;

class DREService
{
    /**
     * Gera o DRE para um período específico.
     */
    public function generate(string $startDate, string $endDate): array
    {
        // Busca todos os planos de contas e agrupa por pai_id em memória para evitar N+1 e LazyLoadingViolation
        $todosPlanos = PlanoConta::all();
        $planosPorPai = $todosPlanos->groupBy(fn ($p) => $p->pai_id ?? 0);

        // Busca todas as transações no período
        $transacoes = TransacaoBancaria::whereBetween('data_transacao', [$startDate, $endDate])->get();

        $raizes = $planosPorPai->get(0, collect());

        $receitas = $this->formatGroup($raizes->where('tipo', 'receita'), $planosPorPai, $transacoes);
        $despesas = $this->formatGroup($raizes->where('tipo', 'despesa'), $planosPorPai, $transacoes);

        $totalReceitas = collect($receitas)->sum('total');
        $totalDespesas = collect($despesas)->sum('total');

        return [
            'receitas' => $receitas,
            'despesas' => $despesas,
            'resumo' => [
                'total_receitas' => $totalReceitas,
                'total_despesas' => $totalDespesas,
                'resultado_liquido' => $totalReceitas - $totalDespesas,
            ],
            'periodo' => [
                'inicio' => $startDate,
                'fim' => $endDate,
            ],
        ];
    }

    /**
     * Formata recursivamente os grupos do plano de contas com seus totais em memória.
     */
    protected function formatGroup(Collection $planos, Collection $planosPorPai, Collection $transacoes): array
    {
        return $planos->map(function ($plano) use ($planosPorPai, $transacoes) {
            // Filhos em memória via agrupamento
            $filhosColecao = $planosPorPai->get($plano->id, collect());
            $filhos = $this->formatGroup($filhosColecao, $planosPorPai, $transacoes);

            // Soma as transações diretas deste plano.
            // Consideramos o valor absoluto, pois a distinção entrada/saída já é dada pelo tipo do plano.
            $totalDireto = $transacoes->where('plano_conta_id', $plano->id)->sum('valor');

            // O total deste nó é o valor direto + soma dos filhos
            $totalGeral = $totalDireto + collect($filhos)->sum('total');

            return [
                'id' => $plano->id,
                'codigo' => $plano->codigo,
                'nome' => $plano->nome,
                'total' => (float) $totalGeral,
                'filhos' => $filhos,
            ];
        })->values()->all();
    }
}
