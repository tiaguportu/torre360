<?php

namespace App\Filament\Resources\Contratos\Pages;

use App\Filament\Exports\ContratoExporter;
use App\Filament\Imports\ContratoImporter;
use App\Filament\Resources\Contratos\ContratoResource;
use App\Models\Contrato;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListContratos extends ListRecords
{
    protected static string $resource = ContratoResource::class;

    public function getTabs(): array
    {
        $statusCounts = Contrato::query()
            ->selectRaw("COALESCE(assinafy_status, 'pendente') as status, count(*) as aggregate")
            ->groupByRaw("COALESCE(assinafy_status, 'pendente')")
            ->pluck('aggregate', 'status');

        $total = $statusCounts->sum();
        $naoEnviados = $statusCounts->get('pendente', 0);
        $aguardando = $statusCounts->get('pending', 0) + $statusCounts->get('enviado', 0);

        $assinados = 0;
        foreach (Contrato::STATUS_ASSINADO as $st) {
            $assinados += $statusCounts->get($st, 0);
        }

        $problema = $statusCounts->get('erro_envio', 0)
            + $statusCounts->get('rejected', 0)
            + $statusCounts->get('refused', 0)
            + $statusCounts->get('canceled', 0)
            + $statusCounts->get('expired', 0);

        return [
            'todos' => Tab::make('Todos')
                ->badge($total),
            'nao_enviados' => Tab::make('Não enviados')
                ->modifyQueryUsing(fn (Builder $query) => $query->where(fn (Builder $q) => $q->whereNull('assinafy_status')->orWhere('assinafy_status', 'pendente')))
                ->badge($naoEnviados)
                ->badgeColor('gray'),
            'aguardando' => Tab::make('Aguardando')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('assinafy_status', ['pending', 'enviado']))
                ->badge($aguardando)
                ->badgeColor('warning'),
            'assinados' => Tab::make('Assinados')
                ->modifyQueryUsing(fn (Builder $query) => $query->assinado())
                ->badge($assinados)
                ->badgeColor('success'),
            'problema' => Tab::make('Com problema')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('assinafy_status', ['erro_envio', 'rejected', 'refused', 'canceled', 'expired']))
                ->badge($problema)
                ->badgeColor('danger'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ImportAction::make()
                ->importer(ContratoImporter::class)
                ->visible(fn (): bool => auth()->user()->can('import', Contrato::class)),
            ExportAction::make()
                ->exporter(ContratoExporter::class)
                ->visible(fn (): bool => auth()->user()->can('export', Contrato::class)),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Gestão de Contratos')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                        ]),
                ]),
        ];
    }

    private function getHelpContent(): string
    {
        return view('filament.resources.contratos.help-list', [
            'user' => auth()->user(),
        ])->render();
    }
}
