<?php

namespace App\Filament\Resources\Interessados\Pages;

use App\Filament\Resources\Interessados\Actions\ImportarLeadIaAction;
use App\Filament\Resources\Interessados\InteressadoResource;
use App\Filament\Widgets\CrmFollowUpCalendarWidget;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListInteressados extends ListRecords
{
    protected static string $resource = InteressadoResource::class;

    protected function getFooterWidgets(): array
    {
        return [
            CrmFollowUpCalendarWidget::make(),
        ];
    }

    public function getTabs(): array
    {
        $base = fn () => InteressadoResource::getEloquentQuery();
        $corteQuente = (int) config('lead_score.faixas_cor.quente', 70);
        $quentes = fn (Builder $query): Builder => $query->where(fn (Builder $q) => $q
            ->where('temperatura', 'quente')
            ->orWhere('lead_score', '>=', $corteQuente));

        return [
            'todos' => Tab::make('Todos')
                ->icon('heroicon-o-users')
                ->badge($base()->count()),
            'precisa_contato' => Tab::make('Precisa de contato')
                ->icon('heroicon-o-exclamation-triangle')
                ->modifyQueryUsing(fn (Builder $query) => $query->ativos()->precisaContato())
                ->badge($base()->ativos()->precisaContato()->count())
                ->badgeColor('danger'),
            'estagnados' => Tab::make('Estagnados')
                ->icon('heroicon-o-clock')
                ->modifyQueryUsing(fn (Builder $query) => $query->ativos()->estagnados())
                ->badge($base()->ativos()->estagnados()->count())
                ->badgeColor('warning'),
            'quentes' => Tab::make('Quentes')
                ->icon('heroicon-o-fire')
                ->modifyQueryUsing(fn (Builder $query) => $quentes($query->ativos()))
                ->badge($quentes($base()->ativos())->count())
                ->badgeColor('success'),
            'ativos' => Tab::make('Em andamento')
                ->icon('heroicon-o-arrow-path')
                ->modifyQueryUsing(fn (Builder $query) => $query->ativos())
                ->badge($base()->ativos()->count())
                ->badgeColor('info'),
            'finalizados' => Tab::make('Finalizados')
                ->icon('heroicon-o-check-badge')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('status', fn (Builder $q) => $q->where('is_final', true)))
                ->badge($base()->whereHas('status', fn (Builder $q) => $q->where('is_final', true))->count())
                ->badgeColor('gray'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ImportarLeadIaAction::make(),
            Action::make('kanban')
                ->label('Ver Kanban')
                ->icon('heroicon-o-view-columns')
                ->color('info')
                ->url(InteressadoResource::getUrl('kanban')),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: CRM (Interessados)')
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
        $user = auth()->user();

        $canCreate = $user->can('Create:Interessado');
        $canUpdate = $user->can('Update:Interessado');

        $html = '<p>Nesta página você faz a gestão dos contatos interessados na escola (prospecção).</p>';
        $html .= '<h3>O que você pode fazer?</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Prospecção:</strong> Visualize a lista de pessoas que entraram em contato demonstrando interesse.</li>';

        if ($canCreate) {
            $html .= '<li><strong>Novo Interessado:</strong> Registre um novo contato vindo do site ou presencial.</li>';
        }

        if ($canUpdate) {
            $html .= '<li><strong>Seguimento:</strong> Atualize o status do interessado (ex: Agendou visita, Matriculado, Desistiu).</li>';
        }

        $html .= '<li><strong>Histórico:</strong> Registre as interações e observações de cada contato para não perder o fio da meada.</li>';
        $html .= '</ul>';

        return $html;
    }
}
