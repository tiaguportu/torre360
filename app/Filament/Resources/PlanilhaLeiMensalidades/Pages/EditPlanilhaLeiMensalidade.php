<?php

namespace App\Filament\Resources\PlanilhaLeiMensalidades\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\PlanilhaLeiMensalidades\PlanilhaLeiMensalidadeResource;
use App\Services\PlanilhaLeiMensalidadeService;
use App\Support\HelpContent;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class EditPlanilhaLeiMensalidade extends EditRecord
{
    use HasAjudaAction;

    protected static string $resource = PlanilhaLeiMensalidadeResource::class;

    protected function getHeaderActions(): array
    {
        $conteudo = HelpContent::make(
            '✏️',
            'Editar Planilha de Reajuste Anual',
            'Revise a memória de cálculo ou ajuste a meta pedagógica e justificativa para a publicação oficial.'
        );

        return [
            Action::make('espelhoOficial')
                ->label('Ver Espelho Oficial')
                ->icon('heroicon-o-document-text')
                ->color('primary')
                ->modalHeading('Demonstrativo Oficial de Custos (Lei 9.870/99)')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->modalContent(fn () => new HtmlString(
                    app(PlanilhaLeiMensalidadeService::class)->gerarEspelhoOficialHtml($this->getRecord())
                )),

            DeleteAction::make(),
            $this->ajudaAction('Editar Planilha Lei 9.870/99', $conteudo),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $service = app(PlanilhaLeiMensalidadeService::class);
        $indices = $service->calcularIndices($data);
        $dadosConsolidados = array_merge($data, $indices);

        $record->update($dadosConsolidados);

        return $record;
    }
}
