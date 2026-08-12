<?php

namespace App\Filament\Resources\Contratos\RelationManagers;

use App\Enums\StatusFatura;
use App\Filament\Resources\Faturas\Schemas\FaturaForm;
use App\Filament\Resources\Faturas\Tables\FaturasTable;
use App\Models\Banco;
use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\TransacaoBancaria;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
                Action::make('dar_baixa')
                    ->label('Dar Baixa')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (Fatura $record) => $record->status !== StatusFatura::Pago)
                    ->schema([
                        Select::make('banco_id')
                            ->label('Banco')
                            ->options(Banco::where('is_active', true)->pluck('nome', 'id'))
                            ->required(),
                        TextInput::make('valor')
                            ->label('Valor Recebido (R$)')
                            ->prefix('R$')
                            ->numeric()
                            ->required()
                            ->default(fn (Fatura $record) => $record->valor_restante),
                        DatePicker::make('data_transacao')
                            ->label('Data do Pagamento')
                            ->required()
                            ->default(now())
                            ->native(false),
                        TextInput::make('descricao')
                            ->label('Observação')
                            ->placeholder('Ex: Pago via PIX, Boleto, Dinheiro...'),
                    ])
                    ->modalHeading('Dar Baixa na Fatura')
                    ->modalDescription(fn (Fatura $record) => "Fatura #{$record->id} — Saldo devedor: R$ ".number_format($record->valor_restante, 2, ',', '.'))
                    ->modalSubmitActionLabel('Confirmar Pagamento')
                    ->action(function (array $data, Fatura $record): void {
                        TransacaoBancaria::create([
                            'banco_id' => $data['banco_id'],
                            'fatura_id' => $record->id,
                            'tipo' => 'entrada',
                            'valor' => $data['valor'],
                            'data_transacao' => $data['data_transacao'],
                            'descricao' => $data['descricao'] ?? "Baixa manual — Fatura #{$record->id}",
                            'conciliado' => true,
                        ]);

                        $record->refresh();
                        $novoSaldo = $record->valor_restante;

                        if ($novoSaldo <= 0) {
                            $record->update(['status' => StatusFatura::Pago]);
                        } elseif ($record->status === StatusFatura::Pendente || $record->status === StatusFatura::Atrasado) {
                            $record->update(['status' => StatusFatura::Parcial]);
                        }

                        Notification::make()
                            ->title('Baixa registrada com sucesso!')
                            ->body($novoSaldo <= 0 ? 'Fatura marcada como PAGA.' : 'Pagamento parcial registrado. Saldo restante: R$ '.number_format(max(0, $novoSaldo), 2, ',', '.'))
                            ->success()
                            ->send();
                    }),
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
