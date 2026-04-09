<?php

namespace App\Filament\Resources\Interessados\Pages;

use App\Filament\Resources\Interessados\InteressadoResource;
use App\Models\Interessado;
use App\Models\StatusInteressado;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;

class KanbanInteressados extends Page
{
    protected static string $resource = InteressadoResource::class;

    protected string $view = 'filament.resources.interessados.pages.kanban-interessados';

    protected ?string $title = 'Funil de Vendas (CRM)';

    protected static ?string $slug = 'kanban';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('list')
                ->label('Ver Lista')
                ->icon('heroicon-o-list-bullet')
                ->color('info')
                ->url(InteressadoResource::getUrl('index')),
        ];
    }

    public function getStatuses(): Collection
    {
        return StatusInteressado::orderBy('ordem')->get();
    }

    public function getInteressados(): Collection
    {
        return Interessado::with(['pessoa', 'status', 'origem'])->get();
    }

    public function updateRecordStatus($recordId, $statusId): void
    {
        $record = Interessado::find($recordId);
        if ($record) {
            $record->update(['status_interessado_id' => $statusId]);

            Notification::make()
                ->title('Status atualizado!')
                ->success()
                ->send();
        }
    }
}
