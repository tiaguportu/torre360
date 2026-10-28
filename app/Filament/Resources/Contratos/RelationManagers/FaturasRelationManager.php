<?php

namespace App\Filament\Resources\Contratos\RelationManagers;

use App\Filament\Exports\FaturaExporter;
use App\Filament\Imports\FaturaImporter;
use App\Filament\Resources\Faturas\Schemas\FaturaForm;
use App\Filament\Resources\Faturas\Tables\FaturasTable;
use App\Models\Contrato;
use App\Models\Fatura;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class FaturasRelationManager extends RelationManager
{
    protected static string $relationship = 'faturas';

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Schema $schema): Schema
    {
        return FaturaForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return FaturasTable::configure($table)
            ->modifyQueryUsing(fn ($query) => $query->with(['itens', 'transacoes']))
            ->headerActions([
                $this->configurarConfirmacaoResetAssinafy(
                    CreateAction::make(),
                    tituloAcao: 'Confirmar Inclusão de Fatura',
                    descricaoAcao: 'incluir uma nova fatura',
                    submitLabel: 'Sim, incluir fatura e resetar assinatura',
                    motivoNotificacao: 'incluída',
                ),
                ImportAction::make()
                    ->label('Importar faturas')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('warning')
                    ->importer(FaturaImporter::class)
                    ->options(fn (RelationManager $livewire): array => ['contrato_id' => $livewire->getOwnerRecord()->getKey()])
                    ->visible(fn (): bool => (bool) auth()->user()?->can('import', Fatura::class))
                    ->modalDescription(function (ImportAction $action, RelationManager $livewire): Htmlable|string|null {
                        $downloadExample = $action->getModalAction('downloadExample');
                        if ($livewire->getOwnerRecord()?->jaEnviadoAssinafy()) {
                            return new HtmlString(
                                '<div class="mb-4 text-sm text-warning-600 dark:text-warning-400 font-medium">Atenção: Este contrato já foi enviado para a plataforma Assinafy. Ao importar faturas, os dados de assinatura serão resetados e o contrato precisará ser enviado novamente.</div>'.
                                ($downloadExample ? $downloadExample->toHtml() : '')
                            );
                        }

                        return $downloadExample;
                    }),
                ExportAction::make()
                    ->label('Exportar faturas')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->exporter(FaturaExporter::class)
                    ->visible(fn (): bool => (bool) auth()->user()?->can('export', Fatura::class)),
            ])
            ->recordActions([
                FaturasTable::darBaixaAction(),
                $this->configurarConfirmacaoResetAssinafy(
                    EditAction::make(),
                    tituloAcao: 'Confirmar Alteração de Fatura',
                    descricaoAcao: 'alterar esta fatura',
                    submitLabel: 'Sim, alterar fatura e resetar assinatura',
                    motivoNotificacao: 'alterada',
                ),
                $this->configurarConfirmacaoResetAssinafy(
                    DeleteAction::make(),
                    tituloAcao: 'Confirmar Exclusão de Fatura',
                    descricaoAcao: 'excluir esta fatura',
                    submitLabel: 'Sim, excluir fatura e resetar assinatura',
                    motivoNotificacao: 'excluída',
                ),
            ])
            ->stackedOnMobile();
    }

    private function configurarConfirmacaoResetAssinafy(
        Action $action,
        string $tituloAcao,
        string $descricaoAcao,
        string $submitLabel,
        string $motivoNotificacao
    ): Action {
        return $action
            ->requiresConfirmation(fn (RelationManager $livewire): bool => $livewire->getOwnerRecord()?->jaEnviadoAssinafy() ?? false)
            ->modalHeading($tituloAcao)
            ->modalDescription("Atenção: Este contrato já foi enviado para a plataforma Assinafy. Ao {$descricaoAcao}, os dados de assinatura serão resetados e o contrato precisará ser enviado novamente. Deseja continuar?")
            ->modalSubmitActionLabel($submitLabel)
            ->after(function (RelationManager $livewire) use ($motivoNotificacao): void {
                /** @var Contrato|null $contrato */
                $contrato = $livewire->getOwnerRecord();
                if ($contrato && $contrato->jaEnviadoAssinafy()) {
                    $contrato->resetAssinafyState();
                    Notification::make()
                        ->title('Assinatura Resetada')
                        ->body("Como uma fatura foi {$motivoNotificacao}, o contrato precisará ser enviado novamente para assinatura.")
                        ->warning()
                        ->send();
                }
            });
    }
}
