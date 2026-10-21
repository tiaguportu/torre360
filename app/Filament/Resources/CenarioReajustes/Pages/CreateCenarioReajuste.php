<?php

declare(strict_types=1);

namespace App\Filament\Resources\CenarioReajustes\Pages;

use App\Enums\ArredondamentoReajuste;
use App\Exceptions\ReajusteMensalidadeException;
use App\Filament\Resources\CenarioReajustes\CenarioReajusteResource;
use App\Models\PeriodoRematricula;
use App\Models\PlanilhaLeiMensalidade;
use App\Services\ReajusteMensalidadeService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCenarioReajuste extends CreateRecord
{
    protected static string $resource = CenarioReajusteResource::class;

    /**
     * O cenário nasce no serviço, que já monta um item por série a partir dos contratos do período de origem.
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            $cenario = app(ReajusteMensalidadeService::class)->simular(
                PeriodoRematricula::query()->findOrFail($data['periodo_rematricula_id']),
                (string) $data['nome'],
                (float) $data['percentual_padrao'],
                $data['arredondamento'] instanceof ArredondamentoReajuste ? $data['arredondamento'] : ArredondamentoReajuste::from($data['arredondamento']),
                filled($data['planilha_lei_id'] ?? null) ? PlanilhaLeiMensalidade::query()->find($data['planilha_lei_id']) : null,
                auth()->user(),
            );
        } catch (ReajusteMensalidadeException $e) {
            Notification::make()->title('Não foi possível simular')->body($e->getMessage())->danger()->persistent()->send();

            $this->halt();
        }

        if (filled($data['observacao'] ?? null)) {
            $cenario->update(['observacao' => $data['observacao']]);
        }

        return $cenario;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
