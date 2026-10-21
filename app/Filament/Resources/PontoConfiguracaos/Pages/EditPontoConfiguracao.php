<?php

declare(strict_types=1);

namespace App\Filament\Resources\PontoConfiguracaos\Pages;

use App\Exceptions\PontoException;
use App\Filament\Resources\PontoConfiguracaos\PontoConfiguracaoResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPontoConfiguracao extends EditRecord
{
    protected static string $resource = PontoConfiguracaoResource::class;

    /**
     * O modelo recusa um modo oficial com requisitos pendentes; aqui a recusa vira aviso na tela em vez de erro.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return parent::handleRecordUpdate($record, $data);
        } catch (PontoException $e) {
            Notification::make()->title('Configuração não salva')->body($e->getMessage())->danger()->persistent()->send();

            $this->halt();
        }
    }
}
