<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orcamentos\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\Orcamentos\OrcamentoResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOrcamentos extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = OrcamentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaAction('Orçamento Anual', HelpContent::make('🏦', 'Orçamento anual', 'O que a escola pretende receber e gastar em cada conta, mês a mês.')
                ->passos('🔁 Como usar', [
                    'Crie o orçamento do ano (em branco, ou copiando o anterior com um reajuste) e preencha a planilha: conta do plano × 12 meses.',
                    'Aprove. O orçamento aprovado não muda: para ajustar valores, abra uma revisão (uma nova versão), que vale quando for aprovada.',
                    'Em Orçado × Realizado, acompanhe o desvio mês a mês e receba o alerta quando uma despesa passar do previsto.',
                ])
                ->secao('📌 Bom saber', [
                    ['📊', 'Realizado', 'Vem das transações bancárias classificadas na conta (e nas subcontas), a mesma regra do DRE: não há duas verdades.'],
                    ['🏷️', 'Classificação', 'Movimento sem conta do plano fica fora do realizado. A tela de Orçado × Realizado mostra quanto ficou sem classificar.'],
                    ['🛒', 'Compras', 'A aprovação de uma compra mostra o saldo da conta no orçamento do ano.'],
                ])),
        ];
    }
}
