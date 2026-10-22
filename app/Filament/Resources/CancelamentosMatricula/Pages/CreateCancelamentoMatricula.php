<?php

declare(strict_types=1);

namespace App\Filament\Resources\CancelamentosMatricula\Pages;

use App\Enums\OrigemCancelamentoMatricula;
use App\Filament\Resources\CancelamentosMatricula\CancelamentoMatriculaResource;
use App\Models\CancelamentoMatricula;
use App\Models\Matricula;
use App\Services\CancelamentoMatriculaService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCancelamentoMatricula extends CreateRecord
{
    protected static string $resource = CancelamentoMatriculaResource::class;

    /**
     * Passa pelo serviço, que trava a matrícula, recusa duplicidade e registra o log.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $matricula = Matricula::query()->findOrFail($data['matricula_id']);

        try {
            return app(CancelamentoMatriculaService::class)->solicitar($matricula, $data, auth()->user(), OrigemCancelamentoMatricula::Escola);
        } catch (\DomainException $e) {
            Notification::make()->title('Não foi possível registrar o pedido')->body($e->getMessage())->danger()->send();

            $this->halt();
        }

        // halt() lança a exceção que interrompe a criação; este retorno só satisfaz a assinatura.
        return new CancelamentoMatricula;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
