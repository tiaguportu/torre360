<?php

namespace App\Filament\Resources\Matriculas\Tables;

use App\Filament\Resources\Matriculas\Pages\DocumentosMatricula;
use App\Models\Contrato;
use App\Models\Matricula;
use App\Notifications\DocumentosPendentesNotification;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
                    ->visible(fn (Matricula $record) => $record->hasMissingMandatoryDocuments())
                    ->action(function (Matricula $record) {
                        /** @var Contrato $contrato */
                        $contrato = $record->contrato;

                        if (! $contrato) {
                            Notification::make()
                                ->title('Erro')
                                ->body('Esta matrícula não possui um contrato associado.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $responsaveis = $contrato->responsaveisFinanceiros;
                        $countSent = 0;

                        foreach ($responsaveis as $resp) {
                            $pessoa = $resp->pessoa;
                            if (! $pessoa) {
                                continue;
                            }

                            foreach ($pessoa->users as $user) {
                                if ($user->email) {
                                    $user->notify(new DocumentosPendentesNotification($record));
                                    $countSent++;
                                }
                            }
                        }

                        if ($countSent > 0) {
                            Notification::make()
                                ->title('E-mail enviado!')
                                ->body("O aviso de pendência foi enviado para {$countSent} destinatário(s).")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Nenhum destinatário encontrado')
                                ->body('Não foi possível localizar usuários com e-mail para o responsável financeiro deste contrato.')
                                ->warning()
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
