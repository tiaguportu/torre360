<?php

namespace App\Filament\Resources\PropostaComercials\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\PropostaComercials\PropostaComercialResource;
use App\Services\RevenueManagementService;
use App\Support\HelpContent;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPropostaComercial extends EditRecord
{
    use HasAjudaAction;

    protected static string $resource = PropostaComercialResource::class;

    protected function getHeaderActions(): array
    {
        $conteudo = HelpContent::make(
            '✏️',
            'Editar Proposta Comercial',
            'Ajuste condições comerciais ou altere a validade da proposta antes do fechamento.'
        );

        return [
            DeleteAction::make(),
            $this->ajudaAction('Editar Proposta Comercial', $conteudo),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $statusAntes = $record->status;

        try {
            $record = app(RevenueManagementService::class)->atualizar($record, $data, auth()->user());
        } catch (\DomainException $e) {
            Notification::make()
                ->danger()
                ->title('Condição comercial bloqueada')
                ->body($e->getMessage())
                ->persistent()
                ->send();

            $this->halt();
        }

        // A edição elevou o desconto acima da alçada já aprovada: o consultor precisa saber por que o status mudou.
        if ($record->isPendente() && $statusAntes !== $record->status) {
            Notification::make()
                ->warning()
                ->title('Proposta enviada para nova aprovação')
                ->body("A nova condição exige alçada {$record->nivel_alcada_necessario->getLabel()}. A aprovação anterior deixou de valer e os aprovadores foram avisados.")
                ->persistent()
                ->send();
        }

        return $record;
    }
}
