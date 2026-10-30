<?php

namespace App\Filament\Resources\Preceptorias\Tables;

use App\Exceptions\ConflitoDeHorarioException;
use App\Exceptions\ExclusaoDePreceptoriaRecusadaException;
use App\Models\Matricula;
use App\Models\Preceptoria;
use App\Services\PreceptoriaAgendaService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class PreceptoriasTable
{
    /**
     * A chave "Ocultar ciclos vencidos" como o usuário a vê agora (com filtros adiados, antes de "Aplicar").
     * Sem estado ainda, vale o padrão: ligada.
     */
    private static function ocultandoCiclosVencidos($livewire): bool
    {
        return (bool) ($livewire->getTableFilterFormState('ocultar_ciclos_vencidos')['isActive'] ?? true);
    }

    private static function cicloEscolhido($livewire): int|string|null
    {
        return $livewire->getTableFilterFormState('ciclo_preceptoria_id')['value'] ?? null;
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['professor', 'matricula.pessoa', 'matricula.turma', 'cicloPreceptoria'])
                ->withExists('relatorios')
            )
            ->columns([
                TextColumn::make('data')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn (Preceptoria $record) => $record->isAgendamentoNoDiaSeguinte() ? 'danger' : null)
                    ->icon(fn (Preceptoria $record) => $record->isAgendamentoNoDiaSeguinte() ? Heroicon::OutlinedExclamationTriangle : null)
                    ->tooltip(fn (Preceptoria $record) => $record->isAgendamentoNoDiaSeguinte() ? 'Agendamento para amanhã!' : null),

                TextColumn::make('hora_inicio')
                    ->label('Início')
                    ->time('H:i')
                    ->sortable(),

                TextColumn::make('hora_fim')
                    ->label('Fim')
                    ->time('H:i')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('cicloPreceptoria.nome')
                    ->label('Ciclo')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('professor.nome')
                    ->label('Professor(a)')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('matricula.pessoa.nome')
                    ->label('Aluno')
                    ->searchable()
                    ->placeholder('—'),

                IconColumn::make('relatorio_exists')
                    ->label('Relatório')
                    ->state(fn ($record) => $record->relatorios_exists)
                    ->boolean()
                    ->trueIcon('heroicon-o-document-text')
                    ->falseIcon('heroicon-o-minus-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),

                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])

            ->defaultSort('data', 'desc')
            ->filters([
                // ligado por padrão: quem quer ver o que já passou desliga a chave. Preceptoria sem ciclo não é de ciclo vencido.
                Filter::make('ocultar_ciclos_vencidos')
                    ->label('Ocultar ciclos de preceptoria vencidos')
                    ->toggle()
                    ->default()
                    // live: o seletor de ciclo logo abaixo muda assim que a chave é mexida, antes de "Aplicar"
                    ->modifyFormFieldUsing(fn ($field) => $field->live())
                    ->query(fn (Builder $query) => $query->where(fn (Builder $preceptorias) => $preceptorias
                        ->whereNull('ciclo_preceptoria_id')
                        ->orWhereHas('cicloPreceptoria', fn (Builder $ciclos) => $ciclos->naoVencidos())))
                    ->excludeWhenResolvingRecord(),

                // com a chave ligada só os ciclos ainda válidos (término hoje ou depois); desligada, todos
                SelectFilter::make('ciclo_preceptoria_id')
                    ->label('Ciclo de Preceptoria')
                    ->relationship('cicloPreceptoria', 'nome', fn (Builder $query, $livewire) => $query
                        ->when(
                            self::ocultandoCiclosVencidos($livewire),
                            // o ciclo já escolhido fica na lista, para o rótulo não sumir se a chave for religada
                            fn (Builder $ciclos) => $ciclos->where(fn (Builder $validos) => $validos
                                ->naoVencidos()
                                ->when(self::cicloEscolhido($livewire), fn (Builder $q, $id) => $q->orWhere('id', $id))),
                        )
                        ->orderByDesc('data_inicio')
                        ->orderBy('nome'))
                    ->searchable()
                    ->preload(),

                SelectFilter::make('professor_id')
                    ->label('Professor(a)')
                    ->relationship('professor', 'nome', fn (Builder $query) => $query
                        ->when(
                            session('active_role') === 'professor' && ! in_array(session('active_role'), ['super_admin', 'admin', 'secretaria']),
                            fn ($q) => $q->whereIn('id', auth()->user()?->pessoas->pluck('id'))
                        )
                        ->orderBy('nome')
                    )
                    ->searchable()
                    ->preload(),

                SelectFilter::make('matricula')
                    ->label('Matrícula / Aluno')
                    ->relationship(
                        'matricula',
                        'id',
                        function (Builder $query) {
                            // O rótulo de cada opção (label_exibicao) usa período letivo, turma e aluno
                            $query->with(['periodoLetivo', 'turma', 'pessoa']);

                            $user = auth()->user();
                            $activeRole = session('active_role');

                            if ($activeRole === 'responsavel' && $user?->pessoa) {
                                $alunoIds = $user->pessoa->alunos()->pluck('aluno_id')->toArray();

                                return $query->whereIn('pessoa_id', $alunoIds);
                            }

                            if ($activeRole === 'aluno' && $user?->pessoa) {
                                return $query->where('pessoa_id', $user->pessoa->id);
                            }

                            return $query;
                        }
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn (Matricula $record) => $record->label_exibicao
                    )
                    ->searchable()
                    ->preload(),

                SelectFilter::make('turma_id')
                    ->label('Turma')
                    ->relationship('matricula.turma', 'nome')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('situacao')
                    ->label('Situação')
                    ->placeholder('Todas')
                    ->trueLabel('Agendadas (C/ Aluno)')
                    ->falseLabel('Livres (S/ Aluno)')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('matricula_id'),
                        false: fn (Builder $query) => $query->whereNull('matricula_id'),
                    ),

                TernaryFilter::make('tem_relatorio')
                    ->label('Relatório')
                    ->placeholder('Todos')
                    ->trueLabel('Com Relatório')
                    ->falseLabel('Sem Relatório')
                    ->queries(
                        true: fn (Builder $query) => $query->has('relatorios'),
                        false: fn (Builder $query) => $query->doesntHave('relatorios'),
                    ),

                Filter::make('data')
                    ->form([
                        DatePicker::make('desde')
                            ->label('Desde')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('ate')
                            ->label('Até')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['desde'],
                                fn (Builder $query, $date): Builder => $query->whereDate('data', '>=', $date),
                            )
                            ->when(
                                $data['ate'],
                                fn (Builder $query, $date): Builder => $query->whereDate('data', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['desde'] ?? null) {
                            $indicators[] = 'Desde '.Carbon::parse($data['desde'])->format('d/m/Y');
                        }
                        if ($data['ate'] ?? null) {
                            $indicators[] = 'Até '.Carbon::parse($data['ate'])->format('d/m/Y');
                        }

                        return $indicators;
                    }),

                // as excluídas ficam guardadas: quem pode excluir também as vê e restaura
                TrashedFilter::make()
                    ->visible(fn () => (bool) auth()->user()?->can('DeleteAny:Preceptoria')),
            ])

            ->actions([
                // a tela de uma preceptoria excluída não abre: ela só pode ser restaurada
                ViewAction::make()->hidden(fn (Preceptoria $record) => $record->trashed()),
                EditAction::make()->hidden(fn (Preceptoria $record) => $record->trashed()),
                self::acaoRestaurar(),
                Action::make('relembrar')
                    ->label('Relembrar')
                    ->tooltip(fn (Preceptoria $record) => $record->getLastLembreteNotificationDate()
                        ? 'Último envio: '.$record->getLastLembreteNotificationDate()->format('d/m/Y H:i').' - Clique para enviar novamente.'
                        : 'Enviar lembrete de agendamento por e-mail e notificação (Nenhum envio anterior)')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->color('warning')
                    ->visible(fn (Preceptoria $record) => ! $record->trashed() && $record->isCompletamenteAgendada() && $record->isAgendamentoFuturo())
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar Envio de Lembrete')
                    ->modalDescription(function (Preceptoria $record) {
                        $emails = $record->getNotificationRecipients()->pluck('email');
                        $lastNotification = $record->getLastLembreteNotificationDate();

                        $html = '<div class="space-y-4">';

                        if ($lastNotification) {
                            $html .= '<div class="p-2 bg-warning-500/10 border border-warning-500/20 rounded-lg text-warning-700 text-sm italic">';
                            $html .= '<strong>Última notificação enviada em:</strong> '.$lastNotification->format('d/m/Y H:i');
                            $html .= '</div>';
                        }

                        $html .= '<div>Deseja enviar um lembrete deste agendamento?</div>';

                        if ($emails->isNotEmpty()) {
                            $html .= '<div><strong>Destinatários:</strong><br><span class="text-gray-500">'.$emails->join(', ').'</span></div>';
                        } else {
                            $html .= '<div class="text-danger-600 font-bold">Atenção: Nenhum destinatário com e-mail encontrado!</div>';
                        }

                        $html .= '</div>';

                        return new HtmlString($html);
                    })
                    ->modalSubmitActionLabel('Sim, enviar lembrete')
                    ->action(function (Preceptoria $record) {
                        $result = $record->relembrarAgendamento();
                        $countSent = $result['enviados'];
                        $falhas = $result['falhas'];

                        if ($countSent > 0) {
                            Notification::make()
                                ->title('Lembrete Enviado')
                                ->body("O lembrete foi enviado para {$countSent} destinatário(s).")
                                ->success()
                                ->send();
                        }

                        if (! empty($falhas)) {
                            foreach ($falhas as $email => $erro) {
                                Notification::make()
                                    ->title("Falha no envio: {$email}")
                                    ->body("Erro: {$erro}")
                                    ->danger()
                                    ->send();
                            }
                        }

                        if ($countSent === 0 && empty($falhas)) {
                            Notification::make()
                                ->title('Nenhum destinatário')
                                ->body('Não foram encontrados usuários com e-mail para receber este lembrete.')
                                ->warning()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('clone')
                        ->label('Clonar em Lote')
                        ->icon(Heroicon::OutlinedDocumentDuplicate)
                        ->color('info')
                        ->visible(fn () => auth()->user()?->can('Create:Preceptoria'))
                        ->form([
                            DatePicker::make('data')
                                ->label('Nova Data (Opcional)')
                                ->placeholder('Manter data original')
                                ->native(false)
                                ->displayFormat('d/m/Y'),
                            Select::make('ciclo_preceptoria_id')
                                ->label('Novo Ciclo de Preceptoria (Opcional)')
                                ->placeholder('Manter ciclo original')
                                ->relationship('cicloPreceptoria', 'nome')
                                ->searchable()
                                ->preload(),
                            Select::make('professor_id')
                                ->label('Novo Professor(a) (Opcional)')
                                ->placeholder('Manter professor original')
                                ->relationship('professor', 'nome', fn (Builder $query) => $query
                                    ->when(
                                        session('active_role') === 'professor' && ! in_array(session('active_role'), ['super_admin', 'admin', 'secretaria']),
                                        fn ($q) => $q->whereIn('id', auth()->user()?->pessoas->pluck('id'))
                                    )
                                    ->orderBy('nome')
                                )
                                ->searchable()
                                ->preload(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $updateData = array_filter($data);
                            $clonadas = 0;
                            $recusadas = [];

                            $records->each(function (Preceptoria $record) use ($updateData, &$clonadas, &$recusadas) {
                                $newRecord = $record->replicate([
                                    'matricula_id', // Não copiar o aluno
                                    'relatorios_exists', // vem de withExists() na listagem; não é coluna
                                ]);

                                if (isset($updateData['data'])) {
                                    $newRecord->data = $updateData['data'];
                                }

                                if (isset($updateData['professor_id'])) {
                                    $newRecord->professor_id = $updateData['professor_id'];
                                }

                                if (isset($updateData['ciclo_preceptoria_id'])) {
                                    $newRecord->ciclo_preceptoria_id = $updateData['ciclo_preceptoria_id'];
                                }

                                // sem nova data o clone cairia sobre o original: o professor não pode ter duas no mesmo horário
                                try {
                                    $newRecord->save();
                                    $clonadas++;
                                } catch (ConflitoDeHorarioException $e) {
                                    $recusadas[] = $e->getMessage();
                                }
                            });

                            if ($clonadas > 0) {
                                Notification::make()
                                    ->title("{$clonadas} preceptorias clonadas com sucesso!")
                                    ->success()
                                    ->send();
                            }

                            if ($recusadas !== []) {
                                Notification::make()
                                    ->title(count($recusadas).' preceptoria(s) não clonada(s) por conflito de horário')
                                    ->body(implode(' ', array_slice(array_unique($recusadas), 0, 3)))
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('relembrar_lote')
                        ->label('Enviar Lembretes em Lote')
                        ->icon(Heroicon::OutlinedEnvelope)
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Confirmar Envio de Lembretes em Lote')
                        ->modalDescription(function (Collection $records) {
                            $elegiveis = $records->filter(fn (Preceptoria $record) => $record->isCompletamenteAgendada() && $record->isAgendamentoFuturo());
                            $ignoradas = $records->count() - $elegiveis->count();

                            $html = '<div class="space-y-4">';
                            $html .= '<div>Deseja enviar lembretes para '.$elegiveis->count().' preceptoria(s) selecionada(s)?</div>';

                            if ($ignoradas > 0) {
                                $html .= '<div class="text-gray-500 text-sm italic">'.$ignoradas.' preceptoria(s) serão ignoradas por não estarem completamente agendadas ou já terem ocorrido.</div>';
                            }

                            $html .= '</div>';

                            return new HtmlString($html);
                        })
                        ->modalSubmitActionLabel('Sim, enviar lembretes')
                        ->action(function (Collection $records) {
                            $elegiveis = $records->filter(fn (Preceptoria $record) => $record->isCompletamenteAgendada() && $record->isAgendamentoFuturo());

                            $totalEnviados = 0;
                            $totalFalhas = 0;

                            foreach ($elegiveis as $record) {
                                $result = $record->relembrarAgendamento();
                                $totalEnviados += $result['enviados'];
                                $totalFalhas += count($result['falhas']);
                            }

                            if ($totalEnviados > 0) {
                                Notification::make()
                                    ->title('Lembretes Enviados')
                                    ->body("Foram enviados {$totalEnviados} lembrete(s) para {$elegiveis->count()} preceptoria(s).")
                                    ->success()
                                    ->send();
                            }

                            if ($totalFalhas > 0) {
                                Notification::make()
                                    ->title('Algumas notificações falharam')
                                    ->body("{$totalFalhas} envio(s) falharam. Consulte os logs para mais detalhes.")
                                    ->danger()
                                    ->send();
                            }

                            if ($totalEnviados === 0 && $totalFalhas === 0) {
                                Notification::make()
                                    ->title('Nenhum lembrete enviado')
                                    ->body('Nenhuma das preceptorias selecionadas é elegível ou possui destinatários com e-mail.')
                                    ->warning()
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('editar_lote')
                        ->label('Editar em Lote')
                        ->icon(Heroicon::OutlinedPencilSquare)
                        ->visible(fn () => auth()->user()?->can('Update:Preceptoria'))
                        ->form([
                            DatePicker::make('data')
                                ->label('Data')
                                ->native(false)
                                ->displayFormat('d/m/Y'),
                            Select::make('ciclo_preceptoria_id')
                                ->label('Ciclo de Preceptoria')
                                ->relationship('cicloPreceptoria', 'nome')
                                ->searchable()
                                ->preload(),
                            TimePicker::make('hora_inicio')
                                ->label('Hora Início')
                                ->seconds(false),
                            TimePicker::make('hora_fim')
                                ->label('Hora Fim')
                                ->seconds(false),
                            Select::make('professor_id')
                                ->label('Professor(a)')
                                ->relationship('professor', 'nome', fn (Builder $query) => $query
                                    ->when(
                                        session('active_role') === 'professor' && ! in_array(session('active_role'), ['super_admin', 'admin', 'secretaria']),
                                        fn ($q) => $q->whereIn('id', auth()->user()?->pessoas->pluck('id'))
                                    )
                                    ->orderBy('nome')
                                )
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

                            $atualizadas = 0;
                            $avisados = 0;
                            $recusadas = [];
                            $servico = app(PreceptoriaAgendaService::class);

                            // uma a uma: as que causariam choque de horário (do professor ou do aluno) ficam como estavam;
                            // as que mudaram entram no histórico e quem é afetado é avisado
                            $records->each(function (Preceptoria $record) use ($updateData, $servico, &$atualizadas, &$avisados, &$recusadas) {
                                try {
                                    $avisados += $servico->atualizarComHistorico($record, $updateData)['notificados'];
                                    $atualizadas++;
                                } catch (ConflitoDeHorarioException $e) {
                                    $recusadas[] = $e->getMessage();
                                }
                            });

                            if ($atualizadas > 0) {
                                Notification::make()
                                    ->title("{$atualizadas} preceptorias atualizadas com sucesso!")
                                    ->body($avisados > 0 ? "{$avisados} aviso(s) enviado(s) aos envolvidos." : null)
                                    ->success()
                                    ->send();
                            }

                            if ($recusadas !== []) {
                                Notification::make()
                                    ->title(count($recusadas).' preceptoria(s) não atualizada(s) por conflito de horário')
                                    ->body(implode(' ', array_slice(array_unique($recusadas), 0, 3)))
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion()
                        ->requiresConfirmation()
                        ->modalHeading('Editar Preceptorias em Lote')
                        ->modalDescription('Selecione os novos valores para os campos que deseja atualizar. Campos vazios não serão alterados. As mudanças ficam no histórico de cada preceptoria e, se for futura, os envolvidos são avisados.')
                        ->modalSubmitActionLabel('Atualizar Selecionadas'),

                    self::acaoExcluirEmLote(),
                    self::acaoRestaurarEmLote(),
                ]),
            ])
            ->stackedOnMobile();
    }

    /**
     * O que o modal de confirmação da exclusão diz: o que acontece com o registro e quem será avisado.
     *
     * @param  array{total: int, recusadas: int, avisados: int}  $previa
     */
    public static function descricaoDaExclusao(array $previa): HtmlString
    {
        $excluidas = $previa['total'] - $previa['recusadas'];

        $html = '<div class="space-y-2">';

        if ($excluidas > 0) {
            $html .= '<div>'.($excluidas === 1 ? 'A preceptoria sai' : "{$excluidas} preceptorias saem").' da agenda e fica(m) guardada(s): dá para restaurar depois, pelo filtro de registros excluídos.</div>';
        }

        if ($previa['recusadas'] > 0) {
            $html .= "<div class=\"text-danger-600 font-bold\">{$previa['recusadas']} tem(têm) relatório e não será(ão) excluída(s). Para devolver o horário à agenda, tire o aluno dele.</div>";
        }

        if ($previa['avisados'] > 0) {
            $html .= "<div>{$previa['avisados']} destinatário(s) (professor, aluno e responsáveis) receberá(ão) o aviso de cancelamento.</div>";
        }

        return new HtmlString($html.'</div>');
    }

    private static function acaoExcluirEmLote(): DeleteBulkAction
    {
        $servico = app(PreceptoriaAgendaService::class);
        $avisados = 0;

        return DeleteBulkAction::make()
            ->modalDescription(fn (Collection $records) => self::descricaoDaExclusao($servico->previaDaExclusao($records)))
            ->using(function (Collection $records, DeleteBulkAction $action) use ($servico, &$avisados) {
                $records->each(function (Preceptoria $record) use ($action, $servico, &$avisados) {
                    try {
                        $avisados += $servico->excluirComHistorico($record)['notificados'];
                    } catch (ExclusaoDePreceptoriaRecusadaException) {
                        $action->reportBulkProcessingFailure('com_relatorio', fn (int $count) => $count === 1
                            ? '1 preceptoria tem relatório e não foi excluída.'
                            : "{$count} preceptorias têm relatório e não foram excluídas.");
                    } catch (\Throwable $e) {
                        report($e);
                        $action->reportBulkProcessingFailure();
                    }
                });
            })
            // por referência: uma fn() capturaria o 0 de antes da exclusão
            ->successNotification(function (Notification $notification) use (&$avisados) {
                return $notification->body($avisados > 0 ? "{$avisados} aviso(s) de cancelamento enviado(s)." : null);
            });
    }

    private static function acaoRestaurar(): RestoreAction
    {
        $avisados = 0;

        return RestoreAction::make()
            ->modalDescription('O horário volta à agenda. Sendo futuro e com aluno, o professor, o aluno e os responsáveis são avisados.')
            ->using(function (Preceptoria $record) use (&$avisados) {
                try {
                    $avisados = app(PreceptoriaAgendaService::class)->restaurarComHistorico($record);

                    return true;
                } catch (ConflitoDeHorarioException $e) {
                    // nesse tempo o professor ou o aluno marcou outra no horário
                    Notification::make()->title('Não foi possível restaurar')->body($e->getMessage())->danger()->send();

                    return false;
                }
            })
            ->successNotification(function (Notification $notification) use (&$avisados) {
                return $notification->body($avisados > 0 ? "{$avisados} aviso(s) enviado(s) aos envolvidos." : null);
            });
    }

    private static function acaoRestaurarEmLote(): RestoreBulkAction
    {
        $servico = app(PreceptoriaAgendaService::class);
        $avisados = 0;

        return RestoreBulkAction::make()
            ->modalDescription('Os horários voltam à agenda; os que baterem com outra preceptoria do professor ou do aluno ficam como estão. Os futuros com aluno avisam os envolvidos.')
            ->using(function (Collection $records, RestoreBulkAction $action) use ($servico, &$avisados) {
                $records->each(function (Preceptoria $record) use ($action, $servico, &$avisados) {
                    try {
                        $avisados += $servico->restaurarComHistorico($record);
                    } catch (ConflitoDeHorarioException $e) {
                        $action->reportBulkProcessingFailure($e->getMessage(), $e->getMessage());
                    } catch (\Throwable $e) {
                        report($e);
                        $action->reportBulkProcessingFailure();
                    }
                });
            })
            ->successNotification(function (Notification $notification) use (&$avisados) {
                return $notification->body($avisados > 0 ? "{$avisados} aviso(s) enviado(s) aos envolvidos." : null);
            });
    }
}
