<?php

namespace App\Filament\Resources\Matriculas\Tables;

use App\Filament\Resources\Contratos\ContratoResource;
use App\Filament\Resources\Matriculas\Pages\BoletimMatricula;
use App\Filament\Resources\Matriculas\Pages\DocumentosMatricula;
use App\Models\Contrato;
use App\Models\Curso;
use App\Models\Matricula;
use App\Models\ResponsavelFinanceiro;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class MatriculasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordClasses(fn (Matricula $record) => $record->hasMissingMandatoryDocuments() ? 'bg-danger-500/10 dark:bg-danger-500/20' : null)
            ->columns([
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
                TextColumn::make('periodoLetivo.nome')
                    ->label('Período Letivo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('situacaoMatricula.nome')
                    ->label('Situação')
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
                SelectFilter::make('curso')
                    ->label('Curso')
                    ->options(Curso::all()->pluck('nome_interno', 'id'))
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }

                        return $query->whereHas('turma.serie', function ($q) use ($data) {
                            $q->where('curso_id', $data['value']);
                        });
                    }),
                SelectFilter::make('turma')
                    ->relationship('turma', 'nome')
                    ->preload()
                    ->searchable()
                    ->label('Turma'),
                SelectFilter::make('periodoLetivo')
                    ->relationship('periodoLetivo', 'nome')
                    ->preload()
                    ->searchable()
                    ->label('Período Letivo'),
                SelectFilter::make('situacaoMatricula')
                    ->relationship('situacaoMatricula', 'nome')
                    ->preload()
                    ->searchable()
                    ->label('Situação'),
            ])
            ->actions([
                EditAction::make(),
                Action::make('boletim')
                    ->label('Boletim')
                    ->tooltip('Ver Boletim Escolar')
                    ->icon(Heroicon::OutlinedAcademicCap)
                    ->color('info')
                    ->url(fn (Matricula $record) => BoletimMatricula::getUrl(['record' => $record]))
                    ->visible(fn (Matricula $record) => auth()->user()->can('boletim', $record) && $record->notas()->whereNotNull('valor')->exists()),
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
                    ->modalHeading('Confirmar Envio de Aviso')
                    ->modalDescription(function (Matricula $record) {
                        $emails = $record->getNotificationRecipients()->pluck('email');

                        if ($emails->isEmpty()) {
                            return new HtmlString('<span class="text-danger-600 font-bold">Erro: Nenhum e-mail encontrado para o aluno ou responsáveis desta matrícula.</span>');
                        }

                        return 'Os avisos de pendência serão enviados para os seguintes e-mails: '.$emails->join(', ');
                    })
                    ->visible(fn (Matricula $record) => auth()->user()->can('AvisarPendencia:Matricula') && $record->hasPendingIssues())
                    ->action(function (Matricula $record) {
                        if (! $record->hasPendingIssues()) {
                            Notification::make()
                                ->title('Sem pendências')
                                ->body('Esta matrícula não possui documentos obrigatórios pendentes no momento.')
                                ->info()
                                ->send();

                            return;
                        }

                        $destinatarios = $record->getNotificationRecipients();

                        if ($destinatarios->isEmpty()) {
                            Notification::make()
                                ->title('Erro ao enviar')
                                ->body('Não foi possível localizar e-mails para o aluno ou responsáveis desta matrícula.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $result = $record->notifyMissingMandatoryDocuments();
                        $countSent = $result['enviados'];
                        $falhas = $result['falhas'];

                        if ($countSent > 0) {
                            Notification::make()
                                ->title('E-mail enviado!')
                                ->body("O aviso de pendência foi enviado para {$countSent} destinatário(s) relacionado(s) à matrícula de **{$record->pessoa->nome}**.")
                                ->success()
                                ->send();
                        }

                        if (! empty($falhas)) {
                            foreach ($falhas as $email => $erro) {
                                Notification::make()
                                    ->title("Falha no envio: {$email}")
                                    ->body("O provedor de e-mail retornou o seguinte erro: {$erro}")
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }
                        }
                    }),
                Action::make('gerarContrato')
                    ->label('Gerar Contrato')
                    ->tooltip('Gerar Contrato para esta Matrícula')
                    ->icon(Heroicon::OutlinedDocumentPlus)
                    ->color('success')
                    ->visible(fn (Matricula $record) => ! $record->contrato_id && $record->pessoa->responsaveis()->exists())
                    ->requiresConfirmation()
                    ->modalHeading('Gerar Contrato?')
                    ->modalDescription('As pessoas responsáveis pelo aluno serão vinculadas ao contrato com valor R$ 0,00.')
                    ->modalSubmitActionLabel('Sim, gerar contrato')
                    ->action(function (Matricula $record) {
                        $contrato = Contrato::create([
                            'valor_total' => 0,
                            'data_aceite' => now(),
                        ]);

                        $record->contrato_id = $contrato->id;
                        $record->save();

                        $responsaveis = $record->pessoa->responsaveis;
                        $count = $responsaveis->count();

                        foreach ($responsaveis as $responsavel) {
                            ResponsavelFinanceiro::create([
                                'contrato_id' => $contrato->id,
                                'pessoa_id' => $responsavel->id,
                                'percentual' => 100 / $count,
                            ]);
                        }

                        Notification::make()
                            ->title('Contrato gerado com sucesso!')
                            ->success()
                            ->send();

                        return redirect(ContratoResource::getUrl('edit', ['record' => $contrato->id]));
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('enviar_emails_pendencia_lote')
                        ->label('Avisar Pendências em Lote')
                        ->icon(Heroicon::OutlinedEnvelope)
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Confirmar Envio em Lote')
                        ->modalDescription('Esta ação enviará avisos de pendência para todas as matrículas selecionadas que possuam documentos obrigatórios pendentes e destinatários com e-mail cadastrado.')
                        ->visible(fn () => auth()->user()->can('AvisarPendencia:Matricula'))
                        ->action(function (Collection $records) {
                            $totalSent = 0;
                            $countMatriculasComPendencia = 0;
                            $countMatriculasSemEmail = 0;
                            $todasFalhas = [];

                            foreach ($records as $record) {
                                if ($record->hasPendingIssues()) {
                                    $destinatarios = $record->getNotificationRecipients();

                                    if ($destinatarios->isEmpty()) {
                                        $countMatriculasSemEmail++;

                                        continue;
                                    }

                                    $result = $record->notifyMissingMandatoryDocuments();
                                    $totalSent += $result['enviados'];
                                    $countMatriculasComPendencia++;

                                    if (! empty($result['falhas'])) {
                                        foreach ($result['falhas'] as $email => $erro) {
                                            $todasFalhas[] = "Matrícula de {$record->pessoa->nome} ({$email}): {$erro}";
                                        }
                                    }
                                }
                            }

                            if ($totalSent > 0) {
                                Notification::make()
                                    ->title('Avisos enviados!')
                                    ->body("Foram enviados {$totalSent} e-mails para os responsáveis de {$countMatriculasComPendencia} matrículas.")
                                    ->success()
                                    ->send();
                            }

                            if (! empty($todasFalhas)) {
                                Notification::make()
                                    ->title('Alguns e-mails falharam')
                                    ->body(new HtmlString('As seguintes falhas foram reportadas:<br>'.implode('<br>', $todasFalhas)))
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }

                            if ($countMatriculasSemEmail > 0) {
                                Notification::make()
                                    ->title('Atenção')
                                    ->body("{$countMatriculasSemEmail} matrícula(s) com pendência não puderam ser notificadas por falta de e-mail cadastrado.")
                                    ->warning()
                                    ->persistent()
                                    ->send();
                            }

                            if ($totalSent === 0 && $countMatriculasSemEmail === 0 && empty($todasFalhas)) {
                                Notification::make()
                                    ->title('Nenhuma notificação enviada')
                                    ->body('As matrículas selecionadas não possuem pendências de documentos obrigatórios.')
                                    ->info()
                                    ->send();
                            }
                        }),
                    DeleteBulkAction::make(),
                    BulkAction::make('editar_lote')
                        ->label('Editar em Lote')
                        ->icon(Heroicon::OutlinedPencilSquare)
                        ->form([
                            Select::make('turma_id')
                                ->label('Turma')
                                ->relationship('turma', 'nome')
                                ->searchable()
                                ->preload(),
                            Select::make('periodo_letivo_id')
                                ->label('Período Letivo')
                                ->relationship('periodoLetivo', 'nome')
                                ->searchable()
                                ->preload(),
                            Select::make('situacao_matricula_id')
                                ->label('Situação')
                                ->relationship('situacaoMatricula', 'nome')
                                ->searchable()
                                ->preload(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $updateData = array_filter($data);

                            if (empty($updateData)) {
                                Notification::make()
                                    ->title('Nenhuma alteração selecionada')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $count = $records->count();

                            $records->each(fn (Matricula $record) => $record->update($updateData));

                            Notification::make()
                                ->title("{$count} matrículas atualizadas com sucesso!")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion()
                        ->requiresConfirmation()
                        ->modalHeading('Editar Matrículas em Lote')
                        ->modalDescription('Selecione os novos valores para os campos que deseja atualizar. Campos vazios não serão alterados.')
                        ->modalSubmitActionLabel('Atualizar Selecionadas'),
                ]),
            ])
            ->stackedOnMobile();
    }
}
