<?php

namespace App\Filament\Resources\DocumentoInseridos\Tables;

use App\Enums\SituacaoDocumento;
use App\Models\DocumentoInserido;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class DocumentoInseridosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tipoDocumento.nome')
                    ->label('Documento')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('matricula.id')
                    ->label('Matrícula')
                    ->description(fn ($record): string => $record->matricula?->pessoa?->nome ?? 'N/A')
                    ->searchable(['matricula.id', 'matricula.pessoa.nome'])
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Situação')
                    ->badge()
                    ->sortable(),

                TextColumn::make('status_sugerido')
                    ->label('Pré-triagem IA')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'verificado' => 'success',
                        'rejeitado' => 'danger',
                        'em_analise' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'verificado' => '✓ Sugere Aprovar',
                        'rejeitado' => '✕ Sugere Rejeitar',
                        'em_analise' => '⚠ Requer Análise',
                        default => 'Aguardando IA',
                    })
                    ->tooltip(fn (DocumentoInserido $record): ?string => $record->temAnaliseIa() ? $record->resumoIa() : null)
                    ->sortable(),

                TextColumn::make('nome_arquivo_original')
                    ->label('Arquivo')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Data de Envio')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label('Última Atualização')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Situação Atual')
                    ->options(SituacaoDocumento::class),

                SelectFilter::make('status_sugerido')
                    ->label('Pré-triagem IA')
                    ->options([
                        'verificado' => '✓ Sugere Aprovar (Verificado)',
                        'em_analise' => '⚠ Requer Análise (Em Análise)',
                        'rejeitado' => '✕ Sugere Rejeitar (Rejeitado)',
                    ]),
            ])
            ->recordActions([
                Action::make('acatarSugestaoIa')
                    ->label('Acatar IA')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (DocumentoInserido $record): bool => filled($record->status_sugerido) && $record->status_sugerido !== $record->status?->value)
                    ->requiresConfirmation()
                    ->modalHeading('Acatar Pré-triagem da IA')
                    ->modalDescription(fn (DocumentoInserido $record): string => "Deseja atualizar a situação do documento '{$record->tipoDocumento?->nome}' de '{$record->status?->getLabel()}' para '{$record->status_sugerido}' com base na pré-triagem do Gemini?")
                    ->action(function (DocumentoInserido $record): void {
                        $target = SituacaoDocumento::tryFrom((string) $record->status_sugerido);
                        if ($target) {
                            $record->update(['status' => $target]);
                            Notification::make()
                                ->title('Situação atualizada!')
                                ->body("Documento atualizado para {$target->getLabel()} conforme pré-triagem da IA.")
                                ->success()
                                ->send();
                        }
                    }),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('editarStatus')
                        ->label('Editar Situação em Lote')
                        ->icon('heroicon-o-pencil-square')
                        ->form([
                            Select::make('status')
                                ->label('Nova Situação')
                                ->options(SituacaoDocumento::class)
                                ->required(),
                        ])
                        ->action(fn (Collection $records, array $data) => $records->each->update($data))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }
}
