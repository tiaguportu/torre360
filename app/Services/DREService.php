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
        // Busca planos de contas raízes
        $planoContas = PlanoConta::with('filhos')->whereNull('pai_id')->get();

        // Busca todas as transações no período
        $transacoes = TransacaoBancaria::whereBetween('data_transacao', [$startDate, $endDate])->get();

        $receitas = $this->formatGroup($planoContas->where('tipo', 'receita'), $transacoes);
        $despesas = $this->formatGroup($planoContas->where('tipo', 'despesa'), $transacoes);

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
     * Formata recursivamente os grupos do plano de contas com seus totais.
     */
    protected function formatGroup(Collection $planos, Collection $transacoes): array
    {
        return $planos->map(function ($plano) use ($transacoes) {
            // Chamada recursiva para os filhos
            $filhos = $this->formatGroup($plano->filhos, $transacoes);

            // Soma as transações diretas deste plano.
            // Consideramos o valor absoluto, pois a distinção entrada/saída já é dada pelo tipo do plano.
            $totalDireto = $transacoes->where('plano_conta_id', $plano->id)->sum('valor');

            // O total deste nó é o valor direto + soma dos filhos
            $totalGeral = $totalDireto + collect($filhos)->sum('total');

            return [
                'id' => $plano->id,
                'nome' => $plano->nome,
                'codigo' => $plano->codigo,
                'total' => $totalGeral,
                'filhos' => $filhos,
            ];
        })->values()->toArray();
    }
}
