<?php

namespace App\Filament\Resources\Preceptorias\Tables;

use App\Models\Matricula;
use App\Models\Preceptoria;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class PreceptoriasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['professor', 'matricula.pessoa', 'matricula.turma'])
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
            ])

            ->actions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('relembrar')
                    ->label('Relembrar')
                    ->tooltip(fn (Preceptoria $record) => $record->getLastLembreteNotificationDate()
                        ? 'Último envio: '.$record->getLastLembreteNotificationDate()->format('d/m/Y H:i').' - Clique para enviar novamente.'
                        : 'Enviar lembrete de agendamento por e-mail e notificação (Nenhum envio anterior)')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->color('warning')
                    ->visible(fn (Preceptoria $record) => $record->isCompletamenteAgendada() && $record->isAgendamentoFuturo())
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
                            $count = $records->count();
                            $updateData = array_filter($data);

                            $records->each(function (Preceptoria $record) use ($updateData) {
                                $newRecord = $record->replicate([
                                    'matricula_id', // Não copiar o aluno
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

                                $newRecord->save();
                            });

                            Notification::make()
                                ->title("{$count} preceptorias clonadas com sucesso!")
                                ->success()
                                ->send();
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

                            $count = $records->count();
                            $records->each(fn (Preceptoria $record) => $record->update($updateData));

                            Notification::make()
                                ->title("{$count} preceptorias atualizadas com sucesso!")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion()
                        ->requiresConfirmation()
                        ->modalHeading('Editar Preceptorias em Lote')
                        ->modalDescription('Selecione os novos valores para os campos que deseja atualizar. Campos vazios não serão alterados.')
                        ->modalSubmitActionLabel('Atualizar Selecionadas'),

                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }
}
