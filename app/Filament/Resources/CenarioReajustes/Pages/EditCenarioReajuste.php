<?php

declare(strict_types=1);

namespace App\Filament\Resources\CenarioReajustes\Pages;

use App\Enums\ArredondamentoReajuste;
use App\Exceptions\ReajusteMensalidadeException;
use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\CenarioReajustes\CenarioReajusteResource;
use App\Filament\Resources\CenarioReajustes\Pages\Concerns\TemAcoesDoCenario;
use App\Models\CenarioReajuste;
use App\Services\ReajusteMensalidadeService;
use App\Support\HelpContent;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditCenarioReajuste extends EditRecord
{
    use HasAjudaAction;
    use TemAcoesDoCenario;

    protected static string $resource = CenarioReajusteResource::class;

    /**
     * Mudar o percentual padrão ou o arredondamento recalcula todas as séries; os demais campos são gravados direto.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var CenarioReajuste $record */
        try {
            $record = app(ReajusteMensalidadeService::class)->atualizarCenario(
                $record,
                (float) $data['percentual_padrao'],
                $data['arredondamento'] instanceof ArredondamentoReajuste ? $data['arredondamento'] : ArredondamentoReajuste::from($data['arredondamento']),
            );
        } catch (ReajusteMensalidadeException $e) {
            Notification::make()->title('Não foi possível salvar')->body($e->getMessage())->danger()->send();

            $this->halt();
        }

        $record->update([
            'nome' => $data['nome'],
            'planilha_lei_id' => $data['planilha_lei_id'] ?? null,
            'observacao' => $data['observacao'] ?? null,
        ]);

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            ...$this->acoesDoCenario(),
            DeleteAction::make()->visible(fn (): bool => (bool) auth()->user()?->can('delete', $this->getRecord())),
            $this->ajudaAction('Simulação de reajuste', HelpContent::make('🧮', 'Simulação de reajuste', 'Ajuste o percentual e as séries, confira a projeção e aprove.')
                ->alerta('Ao salvar um novo percentual padrão, todas as séries sem percentual próprio são recalculadas.')),
        ];
    }
}
