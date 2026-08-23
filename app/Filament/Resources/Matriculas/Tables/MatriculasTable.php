<?php

namespace App\Filament\Resources\Matriculas\Tables;

use App\Enums\SituacaoDocumento;
use App\Enums\SituacaoMatricula;
use App\Filament\Resources\Contratos\ContratoResource;
use App\Filament\Resources\Matriculas\Pages\BoletimMatricula;
use App\Filament\Resources\Matriculas\Pages\DocumentosMatricula;
use App\Filament\Resources\Pessoas\PessoaResource;
use App\Models\Contrato;
use App\Models\Curso;
use App\Models\Matricula;
use App\Models\ResponsavelFinanceiro;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class MatriculasTable
{
    public static function hasIncompleteCadastro($pessoa): bool
    {
        if (! $pessoa) {
            return false;
        }

        return $pessoa->hasIncompleteCadastro();
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->recordClasses(fn (Matricula $record) => ($record->hasMissingMandatoryDocuments() || ($record->pessoa && ! $record->pessoa->responsaveis()->exists()) || $record->hasIncompleteCadastro()) ? 'bg-danger-500/10 dark:bg-danger-500/20' : null)
            ->columns([
                TextColumn::make('pessoa.nome')
                    ->label('Aluno')
                    ->searchable()
                    ->sortable()
                    ->weight(fn (Matricula $record) => ($record->hasMissingMandatoryDocuments() || ($record->pessoa && ! $record->pessoa->responsaveis()->exists()) || $record->hasIncompleteCadastro()) ? 'bold' : null)
                    ->color(fn (Matricula $record) => ($record->hasMissingMandatoryDocuments() || ($record->pessoa && ! $record->pessoa->responsaveis()->exists()) || $record->hasIncompleteCadastro()) ? 'danger' : null)
                    ->url(function (Matricula $record) {
                        if (! $record->pessoa) {
                            return null;
                        }

                        $user = auth()->user();

                        if ($user && $user->can('Update:Pessoa')) {
                            return PessoaResource::getUrl('edit', ['record' => $record->pessoa]);
                        }

                        if ($user && $user->can('View:Pessoa')) {
                            $pages = PessoaResource::getPages();

                            return isset($pages['view'])
                                ? PessoaResource::getUrl('view', ['record' => $record->pessoa])
                                : PessoaResource::getUrl('edit', ['record' => $record->pessoa]);
                        }

                        return null;
                    }),
                TextColumn::make('turma.nome')
                    ->label('Turma')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('periodoLetivo.nome')
                    ->label('Período Letivo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('situacao')
                    ->label('Situação')
                    ->badge()
                    ->sortable(),
                TextColumn::make('data_ativacao')
                    ->label('Data de Ativação')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('data_desativacao')
                    ->label('Data de Desativação')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                SelectFilter::make('situacao')
                    ->options(SituacaoMatricula::class)
                    ->label('Situação')
                    ->default(SituacaoMatricula::ATIVA->value),
                TernaryFilter::make('sem_responsavel')
                    ->label('Responsável Pendente')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('pessoa', function ($q) {
                            $q->whereDoesntHave('responsaveis');
                        }),
                        false: fn (Builder $query) => $query->whereHas('pessoa', function ($q) {
                            $q->whereHas('responsaveis');
                        }),
                    ),
                TernaryFilter::make('documentos_pendentes')
                    ->label('Documento Pendente')
                    ->queries(
                        true: fn (Builder $query) => $query->where(function (Builder $q) {
                            $q->whereHas('turma.serie.curso.documentos', function ($qSub) {
                                $qSub->where('flag_obrigatorio', true)
                                    ->whereRaw('tipo_documento.id NOT IN (SELECT tipo_documento_id FROM documento_inserido WHERE documento_inserido.matricula_id = matricula.id AND documento_inserido.status != ?)', [SituacaoDocumento::REJEITADO->value]);
                            })
                                ->orWhereHas('turma.tiposDocumentos', function ($qSub) {
                                    $qSub->where('flag_obrigatorio', true)
                                        ->whereRaw('tipo_documento.id NOT IN (SELECT tipo_documento_id FROM documento_inserido WHERE documento_inserido.matricula_id = matricula.id AND documento_inserido.status != ?)', [SituacaoDocumento::REJEITADO->value]);
                                })
                                ->orWhereHas('tiposDocumentos', function ($qSub) {
                                    $qSub->where('flag_obrigatorio', true)
                                        ->whereRaw('tipo_documento.id NOT IN (SELECT tipo_documento_id FROM documento_inserido WHERE documento_inserido.matricula_id = matricula.id AND documento_inserido.status != ?)', [SituacaoDocumento::REJEITADO->value]);
                                });
                        }),
                        false: fn (Builder $query) => $query->where(function (Builder $q) {
                            $q->whereDoesntHave('turma.serie.curso.documentos', function ($qSub) {
                                $qSub->where('flag_obrigatorio', true)
                                    ->whereRaw('tipo_documento.id NOT IN (SELECT tipo_documento_id FROM documento_inserido WHERE documento_inserido.matricula_id = matricula.id AND documento_inserido.status != ?)', [SituacaoDocumento::REJEITADO->value]);
                            })
                                ->whereDoesntHave('turma.tiposDocumentos', function ($qSub) {
                                    $qSub->where('flag_obrigatorio', true)
                                        ->whereRaw('tipo_documento.id NOT IN (SELECT tipo_documento_id FROM documento_inserido WHERE documento_inserido.matricula_id = matricula.id AND documento_inserido.status != ?)', [SituacaoDocumento::REJEITADO->value]);
                                })
                                ->whereDoesntHave('tiposDocumentos', function ($qSub) {
                                    $qSub->where('flag_obrigatorio', true)
                                        ->whereRaw('tipo_documento.id NOT IN (SELECT tipo_documento_id FROM documento_inserido WHERE documento_inserido.matricula_id = matricula.id AND documento_inserido.status != ?)', [SituacaoDocumento::REJEITADO->value]);
                                });
                        }),
                    ),
                TernaryFilter::make('dados_pendentes')
                    ->label('Cadastro Pendente')
                    ->queries(
                        true: fn (Builder $query) => $query->comCadastroIncompleto(),
                        false: fn (Builder $query) => $query->comCadastroCompleto(),
                    ),
            ])
            ->actions([
                EditAction::make(),
                Action::make('pendencias')
                    ->label('Pendências')
                    ->hiddenLabel()
                    ->tooltip('Ver Pendências da Matrícula')
                    ->icon(Heroicon::OutlinedExclamationTriangle)
                    ->color('danger')
                    ->badge(function (Matricula $record) {
                        $count = 0;
                        if ($record->pessoa && ! $record->pessoa->responsaveis()->exists()) {
                            $count++;
                        }
                        $incompletas = $record->getPessoasComCadastroIncompleto();
                        $count += $incompletas->count();
                        $count += $record->getMissingMandatoryDocumentsCount();
                        $count += $record->getRejectedDocuments()->count();

                        return $count ?: null;
                    })
                    ->badgeColor('danger')
                    ->visible(fn (Matricula $record) => ($record->pessoa && ! $record->pessoa->responsaveis()->exists()) ||
                        $record->hasIncompleteCadastro() ||
                        $record->hasPendingIssues()
                    )
                    ->modalHeading('Pendências da Matrícula')
                    ->modalDescription(function (Matricula $record) {
                        $html = '<div class="space-y-4 text-left">';

                        // Alerta de Falta de Responsáveis
                        if ($record->pessoa && ! $record->pessoa->responsaveis()->exists()) {
                            $html .= '
                            <div class="p-4 bg-danger-500/10 border border-danger-500/20 rounded-lg text-danger-700 dark:text-danger-400">
                                <div class="flex items-center gap-2 font-bold mb-1">
                                    <span>⚠️ Alerta de Cadastro</span>
                                </div>
                                <p class="text-sm">Este aluno não possui nenhum <strong>Pai, Mãe ou Responsável</strong> associado ao seu cadastro de pessoa.</p>
                                <div class="mt-2">
                                    <a href="'.PessoaResource::getUrl('edit', ['record' => $record->pessoa_id]).'" class="text-xs font-bold underline text-danger-800 dark:text-danger-300 hover:text-danger-900" target="_blank">
                                        Clique aqui para associar responsáveis na ficha do aluno
                                    </a>
                                </div>
                            </div>';
                        }

                        // Alerta de Dados Cadastrais Faltantes
                        $incompletas = $record->getPessoasComCadastroIncompleto();
                        if ($incompletas->isNotEmpty()) {
                            foreach ($incompletas as $item) {
                                $tipoPessoa = $item['tipo'];
                                $pessoa = $item['pessoa'];
                                $camposFormatados = collect($item['campos'])->map(fn ($c) => "<strong>{$c}</strong>")->join(', ', ' e ');

                                $editUrl = PessoaResource::getUrl('edit', ['record' => $pessoa->id]);

                                $html .= '
                                <div class="p-4 bg-danger-500/10 border border-danger-500/20 rounded-lg text-danger-700 dark:text-danger-400">
                                    <div class="flex items-center gap-2 font-bold mb-1">
                                        <span>⚠️ Dados Cadastrais Incompletos ('.$tipoPessoa.')</span>
                                    </div>
                                    <p class="text-sm">O cadastro de <strong>'.e($pessoa->nome ?: 'Sem nome').'</strong> possui campos sem informação: '.$camposFormatados.'.</p>
                                    <div class="mt-2">
                                        <a href="'.$editUrl.'" class="text-xs font-bold underline text-danger-800 dark:text-danger-300 hover:text-danger-900" target="_blank">
                                            Clique aqui para editar os dados cadastrais desta pessoa
                                        </a>
                                    </div>
                                </div>';
                            }
                        }

                        // Alerta de Documentos Faltantes / Rejeitados
                        if ($record->hasPendingIssues()) {
                            $faltantes = $record->getMissingMandatoryDocuments();
                            $rejeitados = $record->getRejectedDocuments();

                            $html .= '
                            <div class="p-4 bg-warning-500/10 border border-warning-500/20 rounded-lg text-warning-700 dark:text-warning-400">
                                <div class="flex items-center gap-2 font-bold mb-1">
                                    <span>📄 Documentos Pendentes</span>
                                </div>';

                            if ($faltantes->isNotEmpty()) {
                                $html .= '<p class="text-sm font-semibold mt-2">Documentos Faltantes:</p>';
                                $html .= '<ul class="list-disc list-inside text-xs text-gray-600 dark:text-gray-400 mt-1">';
                                foreach ($faltantes as $doc) {
                                    $html .= "<li>{$doc->nome}</li>";
                                }
                                $html .= '</ul>';
                            }

                            if ($rejeitados->isNotEmpty()) {
                                $html .= '<p class="text-sm font-semibold mt-2">Documentos Rejeitados:</p>';
                                $html .= '<ul class="list-disc list-inside text-xs text-gray-600 dark:text-gray-400 mt-1">';
                                foreach ($rejeitados as $docInserido) {
                                    $docNome = $docInserido->tipoDocumento->nome;
                                    $obs = $docInserido->observacoes ? " (Motivo: <span class='italic'>{$docInserido->observacoes}</span>)" : '';
                                    $html .= "<li>{$docNome}{$obs}</li>";
                                }
                                $html .= '</ul>';
                            }

                            $html .= '
                                <div class="mt-3">
                                    <a href="'.DocumentosMatricula::getUrl(['record' => $record]).'" class="text-xs font-bold underline text-warning-800 dark:text-warning-300 hover:text-warning-900" target="_blank">
                                        Clique aqui para gerenciar os documentos da matrícula
                                    </a>
                                </div>
                            </div>';
                        }

                        $html .= '</div>';

                        return new HtmlString($html);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar'),
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
                    ->tooltip(fn (Matricula $record) => $record->getLastPendingNotificationDate()
                        ? 'Último envio: '.$record->getLastPendingNotificationDate()->format('d/m/Y H:i').' - Clique para enviar novamente.'
                        : 'Enviar e-mail de aviso de documentos pendentes ao Responsável (Nenhum envio anterior)')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar Envio de Aviso')
                    ->modalDescription(function (Matricula $record) {
                        $emails = $record->getNotificationRecipients()->pluck('email');
                        $faltantes = $record->getMissingMandatoryDocuments();
                        $rejeitados = $record->getRejectedDocuments();

                        if ($emails->isEmpty()) {
                            return new HtmlString('<span class="text-danger-600 font-bold">Erro: Nenhum e-mail encontrado para o aluno ou responsáveis desta matrícula.</span>');
                        }

                        $html = '<div class="space-y-4">';

                        $lastNotification = $record->getLastPendingNotificationDate();
                        if ($lastNotification) {
                            $html .= '<div class="p-2 bg-warning-500/10 border border-warning-500/20 rounded-lg text-warning-700 text-sm italic">';
                            $html .= '<strong>Última notificação enviada em:</strong> '.$lastNotification->format('d/m/Y H:i');
                            $html .= '</div>';
                        }

                        $html .= '<div><strong>Destinatários:</strong><br><span class="text-gray-500">'.$emails->join(', ').'</span></div>';

                        if ($faltantes->isNotEmpty()) {
                            $html .= '<div><strong class="text-danger-600">Documentos Faltantes:</strong><ul class="list-disc list-inside text-sm text-gray-500">';
                            foreach ($faltantes as $doc) {
                                $html .= "<li>{$doc->nome}</li>";
                            }
                            $html .= '</ul></div>';
                        }

                        if ($rejeitados->isNotEmpty()) {
                            $html .= '<div><strong class="text-warning-600">Documentos Rejeitados (necessário reenvio):</strong><ul class="list-disc list-inside text-sm text-gray-500">';
                            foreach ($rejeitados as $docInserido) {
                                $docNome = $docInserido->tipoDocumento->nome;
                                $obs = $docInserido->observacoes ? " (<span class='italic'>Motivo: {$docInserido->observacoes}</span>)" : '';
                                $html .= "<li>{$docNome}{$obs}</li>";
                            }
                            $html .= '</ul></div>';
                        }

                        $html .= '</div>';

                        return new HtmlString($html);
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
                                ->title('Aviso de Pendência Enviado')
                                ->body("O aviso foi enviado para {$countSent} destinatário(s) da matrícula de **{$record->pessoa->nome}**.")
                                ->success()
                                ->send()
                                ->sendToDatabase(auth()->user());
                        }

                        if (! empty($falhas)) {
                            foreach ($falhas as $email => $erro) {
                                Notification::make()
                                    ->title("Falha no envio: {$email}")
                                    ->body("O provedor de e-mail retornou o seguinte erro: {$erro}")
                                    ->send();
                            }
                        }
                    }),
                Action::make('avisar_possibilidade_preceptoria')
                    ->label('Avisar Preceptoria')
                    ->tooltip(fn (Matricula $record) => $record->getLastPreceptoriaNotificationDate()
                        ? 'Último envio: '.$record->getLastPreceptoriaNotificationDate()->format('d/m/Y H:i').' - Clique para enviar novamente.'
                        : 'Avisar sobre disponibilidade de horários de preceptoria (Nenhum envio anterior)')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar Envio de Aviso de Preceptoria')
                    ->modalDescription(function (Matricula $record) {
                        $emails = $record->getNotificationRecipients()->pluck('email');

                        if ($emails->isEmpty()) {
                            return new HtmlString('<span class="text-danger-600 font-bold">Erro: Nenhum e-mail encontrado para o aluno ou responsáveis desta matrícula.</span>');
                        }

                        $html = '<div class="space-y-4">';

                        $lastNotification = $record->getLastPreceptoriaNotificationDate();
                        if ($lastNotification) {
                            $html .= '<div class="p-2 bg-success-500/10 border border-success-500/20 rounded-lg text-success-700 text-sm italic">';
                            $html .= '<strong>Última notificação enviada em:</strong> '.$lastNotification->format('d/m/Y H:i');
                            $html .= '</div>';
                        }

                        $html .= '<div>Gostaria de enviar um aviso de que existem <strong>horários disponíveis</strong> para agendamento de preceptoria?</div>';
                        $html .= '<div><strong>Destinatários:</strong><br><span class="text-gray-500">'.$emails->join(', ').'</span></div>';
                        $html .= '</div>';

                        return new HtmlString($html);
                    })
                    ->visible(fn (Matricula $record) => auth()->user()->can('avisarPossibilidadePreceptoria:Matricula')
                        && ! $record->hasPreceptoriaInActiveCycles()
                        && $record->hasAvailablePreceptoriaWindows()
                    )
                    ->action(function (Matricula $record) {
                        $destinatarios = $record->getNotificationRecipients();

                        if ($destinatarios->isEmpty()) {
                            Notification::make()
                                ->title('Erro ao enviar')
                                ->body('Não foi possível localizar e-mails para o aluno ou responsáveis desta matrícula.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $result = $record->notifyPossibilityPreceptoria();
                        $countSent = $result['enviados'];
                        $falhas = $result['falhas'];

                        if ($countSent > 0) {
                            Notification::make()
                                ->title('Aviso de Preceptoria Enviado')
                                ->body("O aviso de possibilidade de agendamento foi enviado para {$countSent} destinatário(s) da matrícula de **{$record->pessoa->nome}**.")
                                ->success()
                                ->send()
                                ->sendToDatabase(auth()->user());
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
                    ->visible(fn (Matricula $record) => ! $record->contrato()->exists() && $record->pessoa->responsaveis()->exists())
                    ->requiresConfirmation()
                    ->modalHeading('Gerar Contrato?')
                    ->modalDescription('As pessoas responsáveis pelo aluno serão vinculadas ao contrato com valor R$ 0,00.')
                    ->modalSubmitActionLabel('Sim, gerar contrato')
                    ->action(function (Matricula $record) {
                        $contrato = Contrato::create([
                            'matricula_id' => $record->id,
                            'valor_total' => 0,
                            'data_aceite' => now(),
                        ]);

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
                                    ->title('Avisos em Lote Enviados')
                                    ->body("Foram enviados {$totalSent} avisos para os responsáveis de {$countMatriculasComPendencia} matrículas.")
                                    ->success()
                                    ->send()
                                    ->sendToDatabase(auth()->user());
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
                    BulkAction::make('enviar_avisos_preceptoria_lote')
                        ->label('Avisar Preceptoria em Lote')
                        ->icon(Heroicon::OutlinedCalendarDays)
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Confirmar Envio de Avisos de Preceptoria em Lote')
                        ->modalDescription('Esta ação enviará avisos de disponibilidade de horários para agendamento de preceptoria para todas as matrículas selecionadas que ainda não possuem preceptoria agendada nos ciclos vigentes.')
                        ->visible(fn () => auth()->user()->can('AvisarPossibilidadePreceptoria:Matricula') || auth()->user()->can('avisarPossibilidadePreceptoria:Matricula'))
                        ->action(function (Collection $records) {
                            $totalSent = 0;
                            $countMatriculasNotificadas = 0;
                            $matriculasJaAgendadas = [];
                            $matriculasSemJanelas = [];
                            $matriculasSemEmail = [];
                            $todasFalhas = [];

                            foreach ($records as $record) {
                                $alunoNome = $record->pessoa?->nome ?? "Matrícula #{$record->id}";

                                if ($record->hasPreceptoriaInActiveCycles()) {
                                    $matriculasJaAgendadas[] = $alunoNome;

                                    continue;
                                }

                                if (! $record->hasAvailablePreceptoriaWindows()) {
                                    $matriculasSemJanelas[] = $alunoNome;

                                    continue;
                                }

                                $destinatarios = $record->getNotificationRecipients();

                                if ($destinatarios->isEmpty()) {
                                    $matriculasSemEmail[] = $alunoNome;

                                    continue;
                                }

                                $result = $record->notifyPossibilityPreceptoria();
                                $totalSent += $result['enviados'];

                                if ($result['enviados'] > 0) {
                                    $countMatriculasNotificadas++;
                                }

                                if (! empty($result['falhas'])) {
                                    foreach ($result['falhas'] as $email => $erro) {
                                        $todasFalhas[] = "Matrícula de {$alunoNome} ({$email}): {$erro}";
                                    }
                                }
                            }

                            if ($totalSent > 0) {
                                Notification::make()
                                    ->title('Avisos de Preceptoria Enviados')
                                    ->body("Foram enviados {$totalSent} avisos para os responsáveis de {$countMatriculasNotificadas} matrícula(s).")
                                    ->success()
                                    ->send()
                                    ->sendToDatabase(auth()->user());
                            }

                            if (! empty($matriculasJaAgendadas)) {
                                $count = count($matriculasJaAgendadas);
                                Notification::make()
                                    ->title('Matrículas com Agendamento Existente')
                                    ->body(new HtmlString("As seguintes {$count} matrícula(s) foram ignoradas por já possuírem preceptoria agendada:<br>• ".implode('<br>• ', $matriculasJaAgendadas)))
                                    ->info()
                                    ->send();
                            }

                            if (! empty($matriculasSemJanelas)) {
                                $count = count($matriculasSemJanelas);
                                Notification::make()
                                    ->title('Sem Janelas Disponíveis')
                                    ->body(new HtmlString("As seguintes {$count} matrícula(s) não foram notificadas pois não há janelas disponíveis:<br>• ".implode('<br>• ', $matriculasSemJanelas)))
                                    ->warning()
                                    ->send();
                            }

                            if (! empty($matriculasSemEmail)) {
                                $count = count($matriculasSemEmail);
                                Notification::make()
                                    ->title('Sem E-mail Cadastrado')
                                    ->body(new HtmlString("As seguintes {$count} matrícula(s) não puderam ser notificadas por falta de e-mail cadastrado:<br>• ".implode('<br>• ', $matriculasSemEmail)))
                                    ->warning()
                                    ->persistent()
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

                            if ($totalSent === 0 && empty($matriculasJaAgendadas) && empty($matriculasSemJanelas) && empty($matriculasSemEmail) && empty($todasFalhas)) {
                                Notification::make()
                                    ->title('Nenhuma notificação enviada')
                                    ->body('Nenhuma das matrículas selecionadas atende aos critérios para envio do aviso de preceptoria.')
                                    ->info()
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
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
                            Select::make('situacao')
                                ->label('Situação')
                                ->options(SituacaoMatricula::class)
                                ->preload(),
                            DatePicker::make('data_ativacao')
                                ->label('Data de Ativação')
                                ->native(false)
                                ->displayFormat('d/m/Y'),
                            DatePicker::make('data_desativacao')
                                ->label('Data de Desativação')
                                ->native(false)
                                ->displayFormat('d/m/Y'),
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
