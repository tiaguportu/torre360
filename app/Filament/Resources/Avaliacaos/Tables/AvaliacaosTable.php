<?php

namespace App\Filament\Resources\Avaliacaos\Tables;

use App\Filament\Resources\Avaliacaos\AvaliacaoResource;
use App\Filament\Resources\Avaliacaos\Schemas\AvaliacaoForm;
use App\Models\Avaliacao;
use App\Notifications\AvaliacaoPendenteNotification;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class AvaliacaosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([

                TextColumn::make('etapaAvaliativa.nome')
                    ->label('Etapa')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('turma.nome')
                    ->label('Turma')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('disciplina.nome')
                    ->label('Disciplina')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('categoria.nome')
                    ->label('Categoria')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('professor.nome')
                    ->label('Professor')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('data_prevista')
                    ->label('Data Prevista')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('data_ocorrencia')
                    ->label('Data Ocorrência')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('data_limite_lancamento')
                    ->label('Limite Lançamento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('nota_maxima')
                    ->label('MÁX')
                    ->numeric(2)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('peso_etapa_avaliativa')
                    ->label('Peso')
                    ->numeric(2)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('etapa_avaliativa_id')
                    ->relationship('etapaAvaliativa', 'nome')
                    ->label('Etapa')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('categoria_avaliacao_id')
                    ->relationship('categoria', 'nome')
                    ->label('Categoria')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('turma_id')
                    ->relationship('turma', 'nome')
                    ->label('Turma')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('disciplina_id')
                    ->relationship('disciplina', 'nome')
                    ->label('Disciplina')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('professor_id')
                    ->relationship('professor', 'nome')
                    ->label('Professor')
                    ->searchable()
                    ->preload()
                    ->hidden(fn () => auth()->user()?->hasRole('professor')),
            ])
            ->actions([
                ActionGroup::make([
                    EditAction::make(),
                    ReplicateAction::make()
                        ->excludeAttributes(['matriculas_count', 'notas_count'])
                        ->before(fn (Avaliacao $record) => $record->data_prevista = now())
                        ->label('Clonar')
                        ->icon('heroicon-o-document-duplicate'),
                    DeleteAction::make(),
                ]),
                Action::make('notificar_professor')
                    ->label('Notificar Professor')
                    ->tooltip('Enviar lembrete de lançamento de notas pendentes')
                    ->icon('heroicon-o-envelope')
                    ->color('warning')
                    ->action(function (Avaliacao $record) {
                        $professor = $record->professor;
                        $user = $professor?->users->first();

                        if (! $user?->email) {
                            Notification::make()
                                ->danger()
                                ->title('Erro ao enviar')
                                ->body('O professor associado não possui um usuário com e-mail cadastrado.')
                                ->send();

                            return;
                        }

                        $user->notify(new AvaliacaoPendenteNotification($record));

                        Notification::make()
                            ->success()
                            ->title('Notificação Enviada')
                            ->body('O professor foi notificado com sucesso.')
                            ->send();
                    })

                    ->visible(fn (Avaliacao $record): bool => ($record->data_prevista?->isPast() || $record->data_prevista?->isToday()) &&
                        ($record->matriculas_count > $record->notas_count)
                    )
                    ->requiresConfirmation(),
                Action::make('lancar_notas')

                    ->label('Notas')
                    ->tooltip('Lançar Notas')
                    ->icon('heroicon-o-academic-cap')
                    ->color('success')
                    ->badge(fn (Avaliacao $record): ?int => ($count = (int) $record->matriculas_count - (int) $record->notas_count) > 0 ? $count : null)
                    ->badgeColor('danger')
                    ->url(fn (Avaliacao $record): string => AvaliacaoResource::getUrl('lancar-notas', ['record' => $record]))
                    ->visible(fn (Avaliacao $record): bool => auth()->user()->can('lancarNotas', $record)),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('bulk_replicate')
                        ->label('Clonar Selecionados')
                        ->icon('heroicon-o-document-duplicate')
                        ->action(fn (Collection $records) => $records->each(function ($record) {
                            $clone = $record->replicate(['matriculas_count', 'notas_count']);
                            $clone->data_prevista = now();
                            $clone->save();
                        })),
                    BulkAction::make('bulk_edit')
                        ->label('Editar Selecionados')
                        ->icon('heroicon-o-pencil-square')
                        ->form(array_map(fn ($component) => $component->required(false), AvaliacaoForm::getSchemaComponents()))
                        ->action(function (Collection $records, array $data) {
                            $records->each->update(array_filter($data));
                        }),
                    BulkAction::make('bulk_notificar_professor')
                        ->label('Notificar Professores Selecionados')
                        ->icon('heroicon-o-envelope')
                        ->color('warning')
                        ->action(function (Collection $records) {
                            $futureAvaliacao = $records->first(fn (Avaliacao $record) => $record->data_prevista?->isFuture());

                            if ($futureAvaliacao) {
                                Notification::make()
                                    ->danger()
                                    ->title('Operação Cancelada')
                                    ->body("A avaliação '{$futureAvaliacao->categoria?->nome}' tem data prevista no futuro ({$futureAvaliacao->data_prevista?->format('d/m/Y')}) e não pode disparar notificação.")
                                    ->persistent()
                                    ->send();

                                return;
                            }

                            $completedAvaliacao = $records->first(fn (Avaliacao $record) => $record->matriculas_count <= $record->notas_count);

                            if ($completedAvaliacao) {
                                Notification::make()
                                    ->danger()
                                    ->title('Operação Cancelada')
                                    ->body("A avaliação '{$completedAvaliacao->categoria?->nome}' já está com todos os lançamentos concluídos.")
                                    ->persistent()
                                    ->send();

                                return;
                            }

                            $enviados = 0;
                            $falhas = 0;

                            $records->each(function (Avaliacao $record) use (&$enviados, &$falhas) {
                                $user = $record->professor?->users->first();
                                if ($user?->email) {
                                    $user->notify(new AvaliacaoPendenteNotification($record));
                                    $enviados++;
                                } else {
                                    $falhas++;
                                }
                            });

                            if ($enviados > 0) {
                                Notification::make()
                                    ->success()
                                    ->title('Notificações Enviadas')
                                    ->body("{$enviados} professor(es) notificado(s) com sucesso.")
                                    ->send();
                            }

                            if ($falhas > 0) {
                                Notification::make()
                                    ->warning()
                                    ->title('Alguns e-mails não enviados')
                                    ->body("{$falhas} registro(s) sem usuário/e-mail vinculado.")
                                    ->send();
                            }
                        })

                        ->requiresConfirmation(),
                    DeleteBulkAction::make(),

                ]),
            ]);
    }
}
