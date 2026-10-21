<?php

declare(strict_types=1);

namespace App\Filament\Resources\PontoConfiguracaos\Pages;

use App\Exceptions\PontoException;
use App\Filament\Resources\PontoConfiguracaos\PontoConfiguracaoResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePontoConfiguracao extends CreateRecord
{
    protected static string $resource = PontoConfiguracaoResource::class;

    /**
     * O modelo recusa um modo oficial com requisitos pendentes; aqui a recusa vira aviso na tela em vez de erro.
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return parent::handleRecordCreation($data);
        } catch (PontoException $e) {
            Notification::make()->title('Configuração não salva')->body($e->getMessage())->danger()->persistent()->send();

            $this->halt();
        }
    }
}
