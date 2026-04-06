<?php

namespace App\Filament\Resources\Matriculas\Tables;

use App\Filament\Resources\Matriculas\Pages\DocumentosMatricula;
use App\Models\Matricula;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class MatriculasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordClasses(fn (Matricula $record) => $record->hasMissingMandatoryDocuments() ? 'bg-danger-500/10 dark:bg-danger-500/20' : null)
            ->columns([
                TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono')
                    ->copyable(),
                TextColumn::make('pessoa.nome')
                    ->label('Aluno')
                    ->searchable()
                    ->sortable()
                    ->weight(fn (Matricula $record) => $record->hasMissingMandatoryDocuments() ? 'bold' : null)
                    ->color(fn (Matricula $record) => $record->hasMissingMandatoryDocuments() ? 'danger' : null),
                TextColumn::make('turma.nome')
                    ->label('Turma')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('situacaoMatricula.nome')
                    ->label('Situação')
                    ->sortable(),
                TextColumn::make('data_matricula')
                    ->label('Data')
                    ->date()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                Action::make('inserir_documentos')
                    ->label('Documentos')
                    ->tooltip('Gerenciar Documentos Obrigatórios')
                    ->icon(Heroicon::OutlinedDocumentPlus)
                    ->color(fn (Matricula $record) => $record->hasMissingMandatoryDocuments() ? 'danger' : 'primary')
                    ->badge(fn (Matricula $record) => $record->getMissingMandatoryDocumentsCount() ?: null)
                    ->badgeColor('danger')
                    ->url(fn (Matricula $record) => DocumentosMatricula::getUrl(['record' => $record])),
                Action::make('enviar_email_pendencia')
                    ->label('Avisar Pendência')
                    ->tooltip('Enviar e-mail de aviso de documentos pendentes ao Responsável')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Matricula $record) => auth()->user()->can('avisarPendencia', $record))
                    ->action(function (Matricula $record) {
                        if (! $record->hasMissingMandatoryDocuments()) {
                            Notification::make()
                                ->title('Sem pendências')
                                ->body('Esta matrícula não possui documentos obrigatórios pendentes no momento.')
                                ->info()
                                ->send();

                            return;
                        }

                        $countSent = $record->notifyMissingMandatoryDocuments();

                        if ($countSent > 0) {
                            Notification::make()
                                ->title('E-mail enviado!')
                                ->body("O aviso de pendência foi enviado para {$countSent} destinatário(s) relacionado(s) à matrícula de **{$record->pessoa->nome}**.")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Nenhum destinatário encontrado')
                                ->body('Não foi possível localizar usuários com e-mail para os responsáveis do contrato.')
                                ->warning()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('enviar_emails_pendencia_lote')
                        ->label('Avisar Pendências em Lote')
                        ->icon(Heroicon::OutlinedEnvelope)
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn () => auth()->user()->can('avisarPendencia', Matricula::class))
                        ->action(function (Collection $records) {
                            $totalSent = 0;
                            $countMatriculas = 0;

                            foreach ($records as $record) {
                                if ($record->hasMissingMandatoryDocuments()) {
                                    $totalSent += $record->notifyMissingMandatoryDocuments();
                                    $countMatriculas++;
                                }
                            }

                            if ($totalSent > 0) {
                                Notification::make()
                                    ->title('Avisos enviados!')
                                    ->body("Foram enviados {$totalSent} e-mails para os responsáveis de {$countMatriculas} matrículas.")
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Nenhuma pendência notificada')
                                    ->body('As matrículas selecionadas não possuem pendências ou responsáveis com e-mail.')
                                    ->warning()
                                    ->send();
                            }
                        }),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
