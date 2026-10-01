<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Models\TransacaoBancaria;
use App\Support\HelpContent;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use UnitEnum;

/**
 * Fluxo de caixa consolidado por mês (entradas, saídas e saldo), com base em
 * `TransacaoBancaria` — a mesma fonte de dados usada pela baixa de faturas, pela
 * conciliação bancária e pelas contas a pagar.
 */
class RelatorioFluxoCaixa extends Page
{
    use HasAjudaAction;
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static UnitEnum|string|null $navigationGroup = 'Financeiro';

    protected static ?string $navigationLabel = 'Fluxo de Caixa';

    protected static ?string $title = 'Fluxo de Caixa';

    protected static ?string $slug = 'financeiro/fluxo-de-caixa';

    protected static ?int $navigationSort = 21;

    protected string $view = 'filament.pages.relatorio-fluxo-caixa';

    /**
     * Quantidade de meses (incluindo o atual) mostrados no relatório.
     */
    protected int $meses = 12;

    /**
     * @return array<int, array{mes: string, label: string, entradas: float, saidas: float, saldo: float}>
     */
    public function getFluxoMensal(): array
    {
        $inicio = now()->startOfMonth()->subMonths($this->meses - 1);

        $transacoes = TransacaoBancaria::query()
            ->where('data_transacao', '>=', $inicio->toDateString())
            ->get(['tipo', 'valor', 'data_transacao']);

        $porMes = $transacoes->groupBy(fn (TransacaoBancaria $t) => CarbonImmutable::parse($t->data_transacao)->format('Y-m'));

        $linhas = [];

        for ($i = 0; $i < $this->meses; $i++) {
            $mes = $inicio->copy()->addMonths($i);
            $chave = $mes->format('Y-m');
            $doMes = $porMes->get($chave, collect());

            $entradas = (float) $doMes->where('tipo', 'entrada')->sum('valor');
            $saidas = (float) $doMes->where('tipo', 'saida')->sum('valor');

            $linhas[] = [
                'mes' => $chave,
                'label' => $mes->translatedFormat('M/Y'),
                'entradas' => $entradas,
                'saidas' => $saidas,
                'saldo' => $entradas - $saidas,
            ];
        }

        return $linhas;
    }

    /**
     * @return array{total_entradas: float, total_saidas: float, saldo_periodo: float}
     */
    public function getResumo(): array
    {
        $linhas = $this->getFluxoMensal();

        $totalEntradas = array_sum(array_column($linhas, 'entradas'));
        $totalSaidas = array_sum(array_column($linhas, 'saidas'));

        return [
            'total_entradas' => $totalEntradas,
            'total_saidas' => $totalSaidas,
            'saldo_periodo' => $totalEntradas - $totalSaidas,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Fluxo de Caixa', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        return HelpContent::make('📊', 'Fluxo de Caixa', 'Entradas, saídas e saldo consolidados dos últimos 12 meses.')
            ->secao('🎯 O que você encontra aqui?', [
                ['📅', 'Meses', 'Um resumo mês a mês com o total de entradas e saídas.'],
                ['💰', 'Saldo', 'A diferença entre entradas e saídas de cada mês e do período todo.'],
            ])
            ->dica('Os valores vêm das transações bancárias já lançadas (baixas manuais, webhook e extratos importados).');
    }
}
