<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReservaEspacos\Pages;

use App\Exceptions\ReservaEspacoIndisponivelException;
use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\ReservaEspacos\ReservaEspacoResource;
use App\Models\Sala;
use App\Services\ReservaEspacoService;
use App\Support\HelpContent;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditReservaEspaco extends EditRecord
{
    use HasAjudaAction;

    protected static string $resource = ReservaEspacoResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $servico = app(ReservaEspacoService::class);

        try {
            $reserva = $servico->atualizar($record, $data, auth()->user());
        } catch (ReservaEspacoIndisponivelException $e) {
            Notification::make()
                ->title('Não foi possível salvar a reserva')
                ->body(implode("\n", $e->mensagens()))
                ->danger()
                ->send();

            $this->halt();
        }

        foreach ($servico->avisos(Sala::findOrFail($reserva->sala_id), $reserva->turma_id) as $aviso) {
            Notification::make()->title('Atenção')->body($aviso)->warning()->send();
        }

        return $reserva;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            $this->ajudaAction('Editar reserva', HelpContent::make('✏️', 'Editar reserva', 'Altere data, horário, espaço ou finalidade.')
                ->alerta('Reservas canceladas ou que já ocorreram não podem ser alteradas.')
                ->dica('Em espaços com aprovação, mudar o horário de uma reserva confirmada a devolve para análise (a não ser que você possa aprovar).')),
        ];
    }
}
