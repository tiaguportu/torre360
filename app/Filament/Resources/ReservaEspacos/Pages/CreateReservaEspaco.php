<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReservaEspacos\Pages;

use App\Enums\StatusReservaEspaco;
use App\Exceptions\ReservaEspacoIndisponivelException;
use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\ReservaEspacos\ReservaEspacoResource;
use App\Models\Sala;
use App\Services\ReservaEspacoService;
use App\Support\HelpContent;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateReservaEspaco extends CreateRecord
{
    use HasAjudaAction;

    protected static string $resource = ReservaEspacoResource::class;

    /**
     * A reserva passa pelo serviço, que confere conflitos dentro de uma transação com a sala travada. Havendo
     * conflito, nada é gravado e o motivo aparece em uma notificação (mesmo padrão da grade horária).
     */
    protected function handleRecordCreation(array $data): Model
    {
        $servico = app(ReservaEspacoService::class);

        try {
            $reserva = $servico->solicitar($data, auth()->user());
        } catch (ReservaEspacoIndisponivelException $e) {
            Notification::make()
                ->title('Espaço indisponível')
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

    protected function getCreatedNotificationTitle(): ?string
    {
        return $this->record->status === StatusReservaEspaco::Solicitada
            ? 'Pedido enviado para aprovação'
            : 'Reserva confirmada';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Nova reserva', HelpContent::make('🆕', 'Nova reserva', 'Peça um espaço para uma data e um horário.')
                ->passos('Como reservar', [
                    'Escolha o espaço, a data e o horário de início e fim.',
                    'Informe a finalidade (e a turma, se for para uma turma específica).',
                    'Salve. Se o horário estiver ocupado, o sistema mostra o motivo e nada é gravado.',
                ])
                ->dica('Antes de pedir, use "Ver ocupação dos espaços" na listagem para achar um horário livre.')),
        ];
    }
}
