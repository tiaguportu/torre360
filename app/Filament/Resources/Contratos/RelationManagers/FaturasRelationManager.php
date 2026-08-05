<?php

namespace App\Filament\Resources\Contratos\RelationManagers;

use App\Filament\Resources\Faturas\Schemas\FaturaForm;
use App\Filament\Resources\Faturas\Tables\FaturasTable;
use App\Models\Contrato;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

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
            ->headerActions([
                CreateAction::make()
                    ->requiresConfirmation(fn (RelationManager $livewire) => $livewire->getOwnerRecord()?->jaEnviadoAssinafy() ?? false)
                    ->modalHeading('Confirmar Inclusão de Fatura')
                    ->modalDescription('Atenção: Este contrato já foi enviado para a plataforma Assinafy. Ao incluir uma nova fatura, os dados de assinatura serão resetados e o contrato precisará ser enviado novamente. Deseja continuar?')
                    ->modalSubmitActionLabel('Sim, incluir fatura e resetar assinatura')
                    ->after(function (RelationManager $livewire): void {
                        /** @var Contrato $contrato */
                        $contrato = $livewire->getOwnerRecord();
                        if ($contrato && $contrato->jaEnviadoAssinafy()) {
                            $contrato->resetAssinafyState();
                            Notification::make()
                                ->title('Assinatura Resetada')
                                ->body('Como uma fatura foi incluída, o contrato precisará ser enviado novamente para assinatura.')
                                ->warning()
                                ->send();
                        }
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->requiresConfirmation(fn (RelationManager $livewire) => $livewire->getOwnerRecord()?->jaEnviadoAssinafy() ?? false)
                    ->modalHeading('Confirmar Alteração de Fatura')
                    ->modalDescription('Atenção: Este contrato já foi enviado para a plataforma Assinafy. Ao alterar esta fatura, os dados de assinatura serão resetados e o contrato precisará ser enviado novamente. Deseja continuar?')
                    ->modalSubmitActionLabel('Sim, alterar fatura e resetar assinatura')
                    ->after(function (RelationManager $livewire): void {
                        /** @var Contrato $contrato */
                        $contrato = $livewire->getOwnerRecord();
                        if ($contrato && $contrato->jaEnviadoAssinafy()) {
                            $contrato->resetAssinafyState();
                            Notification::make()
                                ->title('Assinatura Resetada')
                                ->body('Como uma fatura foi alterada, o contrato precisará ser enviado novamente para assinatura.')
                                ->warning()
                                ->send();
                        }
                    }),
                DeleteAction::make()
                    ->requiresConfirmation(fn (RelationManager $livewire) => $livewire->getOwnerRecord()?->jaEnviadoAssinafy() ?? false)
                    ->modalHeading('Confirmar Exclusão de Fatura')
                    ->modalDescription('Atenção: Este contrato já foi enviado para a plataforma Assinafy. Ao excluir esta fatura, os dados de assinatura serão resetados e o contrato precisará ser enviado novamente. Deseja continuar?')
                    ->modalSubmitActionLabel('Sim, excluir fatura e resetar assinatura')
                    ->after(function (RelationManager $livewire): void {
                        /** @var Contrato $contrato */
                        $contrato = $livewire->getOwnerRecord();
                        if ($contrato && $contrato->jaEnviadoAssinafy()) {
                            $contrato->resetAssinafyState();
                            Notification::make()
                                ->title('Assinatura Resetada')
                                ->body('Como uma fatura foi excluída, o contrato precisará ser enviado novamente para assinatura.')
                                ->warning()
                                ->send();
                        }
                    }),
            ]);
    }
}
