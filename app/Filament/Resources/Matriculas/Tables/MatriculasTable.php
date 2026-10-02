<?php

namespace App\Filament\Resources\Matriculas\Tables;

use App\Enums\SituacaoMatricula;
use App\Enums\TipoPendenciaMatricula;
use App\Filament\Resources\Contratos\ContratoResource;
use App\Filament\Resources\Matriculas\Pages\BoletimMatricula;
use App\Filament\Resources\Matriculas\Pages\DocumentosMatricula;
use App\Filament\Resources\Pessoas\PessoaResource;
use App\Models\Contrato;
use App\Models\Curso;
use App\Models\Matricula;
use App\Models\ResponsavelFinanceiro;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class MatriculasTable
{
    /**
     * @param  bool  $comFiltroSituacao  Inclui o filtro de Situação (padrão: Ativa). A listagem principal
     *                                   dispensa o filtro porque usa abas por situação; quem não tem abas
     *                                   (ex.: relation manager de Turmas) deve ativá-lo.
     */
    public static function configure(Table $table, bool $comFiltroSituacao = false): Table
    {
        // Janelas de preceptoria independem da matrícula: consulta uma única vez por renderização.
        $haJanelasDePreceptoria = null;
        $consultaJanelasDePreceptoria = function (Matricula $record) use (&$haJanelasDePreceptoria): bool {
            return $haJanelasDePreceptoria ??= $record->hasAvailablePreceptoriaWindows();
        };

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with([
                    'serie',
                    'periodoLetivo',
                    'turma.serie.curso.documentos',
                    'turma.tiposDocumentos',
                    'tiposDocumentos',
                    'documentoInseridos.tipoDocumento',
                    'pessoa.nacionalidade',
                    'pessoa.enderecos',
                    'pessoa.responsaveis.nacionalidade',
                    'pessoa.responsaveis.enderecos',
                    'contrato.responsaveisFinanceiros.pessoa.nacionalidade',
                    'contrato.responsaveisFinanceiros.pessoa.enderecos',
                ])
                ->withExists([
                    'notas as tem_notas' => fn (Builder $notas) => $notas->whereNotNull('valor'),
                    'preceptorias as tem_preceptoria_ciclo_vigente' => fn (Builder $preceptorias) => $preceptorias
                        ->whereHas('cicloPreceptoria', fn (Builder $ciclo) => $ciclo->vigentes()),
                ]))
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->persistFiltersInSession()
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->emptyStateIcon(Heroicon::OutlinedIdentification)
            ->emptyStateHeading('Nenhuma matrícula encontrada')
            ->emptyStateDescription('Ajuste a aba e os filtros ou cadastre uma nova matrícula.')
            ->columns([
                TextColumn::make('pessoa.nome')
                    ->label('Aluno')
                    ->description(function (Matricula $record): string {
                        $turma = $record->turma?->nome;

                        if (blank($turma)) {
                            return $record->serie_nome ? "{$record->serie_nome} · sem turma" : 'Sem turma';
                        }

                        return implode(' · ', array_filter([$turma, $record->turma?->serie?->curso?->nome]));
                    })
                    ->weight('bold')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(fn (Builder $q) => $q
                        ->whereHas('pessoa', fn (Builder $pessoa) => $pessoa->where('nome', 'like', "%{$search}%"))
                        ->orWhereHas('turma', fn (Builder $turma) => $turma->where('nome', 'like', "%{$search}%"))))
                    ->sortable()
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
                TextColumn::make('situacao')
                    ->label('Situação')
                    ->badge()
                    ->sortable(),
                TextColumn::make('pendencias')
                    ->label('Pendências')
                    ->state(fn (Matricula $record): array => $record->pendencias->tipos() ?: ['em_dia'])
                    ->badge()
                    // Um badge embaixo do outro: a coluna fica estreita e as ações da linha continuam visíveis sem rolagem lateral.
                    ->listWithLineBreaks()
                    ->extraAttributes(['style' => 'align-items: flex-start;'])
                    ->formatStateUsing(fn (mixed $state, Matricula $record): string => ($tipo = self::tipoPendencia($state))
                        ? $record->pendencias->rotulo($tipo)
                        : 'Em dia')
                    ->color(fn (mixed $state): string|array|null => self::tipoPendencia($state)?->getColor() ?? 'success')
                    ->icon(fn (mixed $state): ?string => self::tipoPendencia($state)?->getIcon() ?? 'heroicon-m-check-circle')
                    ->tooltip(fn (Matricula $record): ?string => $record->pendencias->temPendencias()
                        ? $record->pendencias->detalhes()."\n\nClique para ver os detalhes."
                        : null)
                    ->disabledClick(fn (Matricula $record): bool => ! $record->pendencias->temPendencias())
                    ->action(
                        Action::make('detalharPendencias')
                            ->modalHeading(fn (Matricula $record): string => 'Pendências de '.($record->pessoa?->nome ?? 'matrícula'))
                            ->modalIcon(Heroicon::OutlinedExclamationTriangle)
                            ->modalIconColor('danger')
                            ->modalWidth(Width::Large)
                            ->modalContent(fn (Matricula $record) => view('filament.matriculas.pendencias', [
                                'matricula' => $record,
                                'pendencias' => $record->pendencias,
                            ]))
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Fechar')
                            ->visible(fn (Matricula $record): bool => $record->pendencias->temPendencias())
                    ),
                TextColumn::make('periodoLetivo.nome')
                    ->label('Período Letivo')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                IconColumn::make('contrato')
                    ->label('Contrato')
                    ->state(fn (Matricula $record): bool => $record->contrato !== null)
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedDocumentCheck)
                    ->falseIcon(Heroicon::OutlinedDocumentMinus)
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(fn (Matricula $record): string => $record->contrato ? 'Contrato gerado' : 'Sem contrato')
                    ->toggleable(),
                TextColumn::make('turma.nome')
                    ->label('Turma')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                    ->label('Criada em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Atualizada em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('curso')
                    ->label('Curso')
                    ->options(fn () => Curso::query()->orderBy('nome_interno')->pluck('nome_interno', 'id'))
                    ->searchable()
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
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->label('Turma'),
                SelectFilter::make('periodoLetivo')
                    ->relationship('periodoLetivo', 'nome')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->label('Período Letivo'),
                ...($comFiltroSituacao ? [
                    SelectFilter::make('situacao')
                        ->options(SituacaoMatricula::class)
                        ->label('Situação')
                        ->default(SituacaoMatricula::ATIVA->value),
                ] : []),
                SelectFilter::make('pendencias')
                    ->label('Pendências')
                    ->options(TipoPendenciaMatricula::class)
                    ->multiple()
                    ->query(function (Builder $query, array $data): Builder {
                        $tipos = collect($data['values'] ?? [])
                            ->map(fn (string $valor) => TipoPendenciaMatricula::tryFrom($valor))
                            ->filter();

                        if ($tipos->isEmpty()) {
                            return $query;
                        }

                        return $query->where(function (Builder $q) use ($tipos) {
                            foreach ($tipos as $tipo) {
                                $q->orWhere(fn (Builder $sub) => $sub->comPendencia($tipo));
                            }
                        });
                    }),
                TernaryFilter::make('contrato')
                    ->label('Contrato')
                    ->placeholder('Todos')
                    ->trueLabel('Com contrato')
                    ->falseLabel('Sem contrato')
                    ->queries(
                        true: fn (Builder $query) => $query->has('contrato'),
                        false: fn (Builder $query) => $query->doesntHave('contrato'),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->actions([
                EditAction::make()
                    ->iconButton()
                    ->tooltip('Editar matrícula'),
                Action::make('inserir_documentos')
                    ->label('Documentos')
                    ->tooltip('Gerenciar documentos obrigatórios')
                    ->icon(Heroicon::OutlinedDocumentPlus)
                    ->iconButton()
                    ->color(fn (Matricula $record) => $record->pendencias->documentosFaltantes->isNotEmpty() ? 'danger' : 'primary')
                    ->badge(fn (Matricula $record) => $record->pendencias->documentosFaltantes->count() ?: null)
                    ->badgeColor('danger')
                    ->url(fn (Matricula $record) => DocumentosMatricula::getUrl(['record' => $record])),
                ActionGroup::make([
                    ActionGroup::make([
                        Action::make('boletim')
                            ->label('Boletim')
                            ->icon(Heroicon::OutlinedAcademicCap)
                            ->color('info')
                            ->url(fn (Matricula $record) => BoletimMatricula::getUrl(['record' => $record]))
                            ->visible(fn (Matricula $record) => auth()->user()->can('boletim', $record)
                                && ($record->tem_notas ?? $record->notas()->whereNotNull('valor')->exists())),
                    ])->dropdown(false),
                    ActionGroup::make([
                        Action::make('enviar_email_pendencia')
                            ->label('Avisar pendência por e-mail')
                            ->icon(Heroicon::OutlinedEnvelope)
                            ->color('warning')
                            ->requiresConfirmation()
                            ->modalHeading('Confirmar envio de aviso')
                            ->modalDescription('Confira os destinatários e as pendências antes de confirmar.')
                            ->modalContent(fn (Matricula $record) => view('filament.matriculas.confirmar-aviso', [
                                'destinatarios' => $record->getNotificationRecipients()->pluck('email'),
                                'ultimoEnvio' => $record->getLastPendingNotificationDate(),
                                'mensagem' => null,
                                'faltantes' => $record->pendencias->documentosFaltantes,
                                'rejeitados' => $record->pendencias->documentosRejeitados,
                                'cor' => 'warning',
                            ]))
                            ->visible(fn (Matricula $record) => auth()->user()->can('AvisarPendencia:Matricula')
                                && $record->pendencias->temPendenciaDocumental())
                            ->action(function (Matricula $record) {
                                if (! $record->pendencias->temPendenciaDocumental()) {
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
                            ->label('Avisar preceptoria por e-mail')
                            ->icon(Heroicon::OutlinedCalendarDays)
                            ->color('success')
                            ->requiresConfirmation()
                            ->modalHeading('Confirmar envio de aviso de preceptoria')
                            ->modalDescription('Confira os destinatários antes de confirmar.')
                            ->modalContent(fn (Matricula $record) => view('filament.matriculas.confirmar-aviso', [
                                'destinatarios' => $record->getNotificationRecipients()->pluck('email'),
                                'ultimoEnvio' => $record->getLastPreceptoriaNotificationDate(),
                                'mensagem' => 'Gostaria de enviar um aviso de que existem horários disponíveis para agendamento de preceptoria?',
                                'faltantes' => collect(),
                                'rejeitados' => collect(),
                                'cor' => 'success',
                            ]))
                            ->visible(fn (Matricula $record) => auth()->user()->can('avisarPossibilidadePreceptoria:Matricula')
                                && ! ($record->tem_preceptoria_ciclo_vigente ?? $record->hasPreceptoriaInActiveCycles())
                                && $consultaJanelasDePreceptoria($record)
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
                    ])->dropdown(false),
                    ActionGroup::make([
                        Action::make('gerarContrato')
                            ->label('Gerar contrato')
                            ->icon(Heroicon::OutlinedDocumentPlus)
                            ->color('success')
                            ->visible(fn (Matricula $record) => $record->contrato === null
                                && $record->pessoa !== null
                                && ! $record->estaSemResponsavel())
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
                    ])->dropdown(false),
                ])
                    ->label('Mais ações')
                    ->icon(Heroicon::EllipsisVertical)
                    ->color('gray')
                    ->tooltip('Mais ações'),
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
                                if ($record->pendencias->temPendenciaDocumental()) {
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
                                    ->body(new HtmlString('As seguintes falhas foram reportadas:<br>'.implode('<br>', array_map('e', $todasFalhas))))
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
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('enviar_avisos_preceptoria_lote')
                        ->label('Avisar Preceptoria em Lote')
                        ->icon(Heroicon::OutlinedCalendarDays)
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Confirmar Envio de Avisos de Preceptoria em Lote')
                        ->modalDescription('Esta ação enviará avisos de disponibilidade de horários para agendamento de preceptoria para todas as matrículas selecionadas que ainda não possuem preceptoria agendada nos ciclos vigentes.')
                        ->visible(fn () => auth()->user()->can('AvisarPossibilidadePreceptoria:Matricula') || auth()->user()->can('avisarPossibilidadePreceptoria:Matricula'))
                        ->action(function (Collection $records) use ($consultaJanelasDePreceptoria) {
                            $totalSent = 0;
                            $countMatriculasNotificadas = 0;
                            $matriculasJaAgendadas = [];
                            $matriculasSemJanelas = [];
                            $matriculasSemEmail = [];
                            $todasFalhas = [];

                            foreach ($records as $record) {
                                $alunoNome = $record->pessoa?->nome ?? "Matrícula #{$record->id}";

                                if ($record->tem_preceptoria_ciclo_vigente ?? $record->hasPreceptoriaInActiveCycles()) {
                                    $matriculasJaAgendadas[] = $alunoNome;

                                    continue;
                                }

                                if (! $consultaJanelasDePreceptoria($record)) {
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
                                    ->body(new HtmlString("As seguintes {$count} matrícula(s) foram ignoradas por já possuírem preceptoria agendada:<br>• ".implode('<br>• ', array_map('e', $matriculasJaAgendadas))))
                                    ->info()
                                    ->send();
                            }

                            if (! empty($matriculasSemJanelas)) {
                                $count = count($matriculasSemJanelas);
                                Notification::make()
                                    ->title('Sem Janelas Disponíveis')
                                    ->body(new HtmlString("As seguintes {$count} matrícula(s) não foram notificadas pois não há janelas disponíveis:<br>• ".implode('<br>• ', array_map('e', $matriculasSemJanelas))))
                                    ->warning()
                                    ->send();
                            }

                            if (! empty($matriculasSemEmail)) {
                                $count = count($matriculasSemEmail);
                                Notification::make()
                                    ->title('Sem E-mail Cadastrado')
                                    ->body(new HtmlString("As seguintes {$count} matrícula(s) não puderam ser notificadas por falta de e-mail cadastrado:<br>• ".implode('<br>• ', array_map('e', $matriculasSemEmail))))
                                    ->warning()
                                    ->persistent()
                                    ->send();
                            }

                            if (! empty($todasFalhas)) {
                                Notification::make()
                                    ->title('Alguns e-mails falharam')
                                    ->body(new HtmlString('As seguintes falhas foram reportadas:<br>'.implode('<br>', array_map('e', $todasFalhas))))
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
                    DeleteBulkAction::make()
                        ->color('danger'),
                ]),
            ])
            ->stackedOnMobile();
    }

    /**
     * Converte o estado de uma célula da coluna "Pendências" no tipo correspondente
     * (nulo para o marcador "em dia").
     */
    private static function tipoPendencia(mixed $state): ?TipoPendenciaMatricula
    {
        return match (true) {
            $state instanceof TipoPendenciaMatricula => $state,
            is_string($state) => TipoPendenciaMatricula::tryFrom($state),
            default => null,
        };
    }
}
