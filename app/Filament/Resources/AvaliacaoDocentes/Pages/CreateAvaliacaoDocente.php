<?php

declare(strict_types=1);

namespace App\Filament\Resources\AvaliacaoDocentes\Pages;

use App\Exceptions\AvaliacaoDocenteException;
use App\Filament\Resources\AvaliacaoDocentes\AvaliacaoDocenteResource;
use App\Services\AvaliacaoDocenteService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAvaliacaoDocente extends CreateRecord
{
    protected static string $resource = AvaliacaoDocenteResource::class;

    /**
     * A observação nasce no serviço, que valida o ciclo, a data e a autoavaliação e já cria uma linha por critério
     * do instrumento. Em seguida o avaliador cai direto na tela de notas.
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(AvaliacaoDocenteService::class)->iniciar($data, auth()->user());
        } catch (AvaliacaoDocenteException $e) {
            Notification::make()
                ->title('Não foi possível iniciar a avaliação')
                ->body($e->getMessage())
                ->danger()
                ->send();

            $this->halt();
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Avaliação iniciada: dê as notas aos critérios';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
