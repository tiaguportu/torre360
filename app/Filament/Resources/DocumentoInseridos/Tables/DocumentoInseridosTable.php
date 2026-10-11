<?php

namespace App\Filament\Resources\DocumentoInseridos\Tables;

use App\Enums\SituacaoDocumento;
use App\Models\DocumentoInserido;
use App\Support\PermissaoAcao;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
                    ->authorize(PermissaoAcao::qualquer('Update:DocumentoInserido'))
                    ->visible(fn (DocumentoInserido $record): bool => filled($record->status_sugerido) && $record->status_sugerido !== $record->status?->value)
                    ->requiresConfirmation(fn (DocumentoInserido $record): bool => $record->status_sugerido !== SituacaoDocumento::REJEITADO->value)
                    ->modalHeading('Acatar Pré-triagem da IA')
                    ->modalDescription(fn (DocumentoInserido $record): string => "Deseja atualizar a situação do documento '{$record->tipoDocumento?->nome}' de '{$record->status?->getLabel()}' para '{$record->status_sugerido}' com base na pré-triagem do Gemini?")
                    ->form(function (DocumentoInserido $record): array {
                        if ($record->status_sugerido === SituacaoDocumento::REJEITADO->value) {
                            return [
                                Textarea::make('motivo')
                                    ->label('Motivo da Recusa (visível para os pais no portal)')
                                    ->required()
                                    ->placeholder('Ex: Foto cortada, documento vencido ou ilegível...'),
                            ];
                        }

                        return [];
                    })
                    ->action(function (DocumentoInserido $record, array $data): void {
                        $target = SituacaoDocumento::tryFrom((string) $record->status_sugerido);
                        if ($target) {
                            $record->aplicarSituacao(
                                situacao: $target,
                                motivo: $data['motivo'] ?? null,
                                usuario: auth()->user(),
                                origemIa: true
                            );

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
