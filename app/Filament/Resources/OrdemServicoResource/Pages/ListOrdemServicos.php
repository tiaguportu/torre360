<?php

namespace App\Filament\Resources\OrdemServicoResource\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\OrdemServicoResource;
use App\Support\HelpContent;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOrdemServicos extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = OrdemServicoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            $this->ajudaAction('Ordens de Serviço', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('🛠️', 'Ordens de Serviço', 'Solicitações de manutenção e serviços da instituição.')
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Listagem', 'Veja título, status, prioridade, prazo de conclusão, custo estimado e percentual concluído.'],
                ['📊', 'Indicadores', 'Os cartões no topo mostram OS por status, OS atrasadas e custo estimado total.'],
                $user->can('Create:OrdemServico') ? ['🆕', 'Nova OS', 'Abra uma ordem informando título, prioridade, prazo e custo estimado.'] : null,
                $user->can('Update:OrdemServico') ? ['✏️', 'Editar', 'Atualize status, andamento e custos da ordem.'] : null,
                ['📄', 'Exportar PDF', 'Marque uma ou mais ordens na lista e use a ação em lote "Exportar PDF".'],
                ['🔎', 'Filtros', 'Filtre por status e por prioridade.'],
            ])
            ->dica('Ordens com prazo vencido que não estão concluídas nem canceladas contam como atrasadas. Os cartões acompanham os filtros e a busca da lista.');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            OrdemServicoResource\Widgets\OrdemServicoStats::class,
        ];
    }
}
