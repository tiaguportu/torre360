<?php

namespace App\Filament\Resources\CronogramaAulas\Tables;

use App\Filament\Resources\CronogramaAulas\CronogramaAulaResource;
use App\Models\CronogramaAula;
use App\Models\Matricula;
use App\Notifications\FrequenciaPendenteNotification;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class CronogramaAulasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('periodoLetivo.nome')
                    ->label('Período Letivo')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('turma.nome')
                    ->label('Turma')
                    ->sortable(),
                TextColumn::make('disciplina.nome')
                    ->label('Disciplina')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('professor.nome')
                    ->label('Professor')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('data')
                    ->date()
                    ->sortable(),
                TextColumn::make('hora_inicio')
                    ->time()
                    ->sortable(),
                TextColumn::make('hora_fim')
                    ->time(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('periodo_letivo_id')
                    ->label('Período Letivo')
                    ->relationship('periodoLetivo', 'nome')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('turma_id')
                    ->label('Turma')
                    ->relationship('turma', 'nome')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('disciplina_id')
                    ->label('Disciplina')
                    ->relationship('disciplina', 'nome')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('pessoa_id')
                    ->label('Professor')
                    ->relationship('professor', 'nome')
                    ->searchable()
                    ->preload()
                    ->hidden(fn () => auth()->user()?->hasRole('professor')),
                Filter::make('periodo_data')
                    ->form([
                        DatePicker::make('data_inicio')
                            ->label('Data Início')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('data_fim')
                            ->label('Data Fim')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['data_inicio'],
                                fn (Builder $query, $date): Builder => $query->whereDate('data', '>=', $date),
                            )
                            ->when(
                                $data['data_fim'],
                                fn (Builder $query, $date): Builder => $query->whereDate('data', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (! $data['data_inicio'] && ! $data['data_fim']) {
                            return null;
                        }

                        $indicator = 'Período: ';

                        if ($data['data_inicio']) {
                            $indicator .= 'de '.Carbon::parse($data['data_inicio'])->format('d/m/Y');
                        }

                        if ($data['data_fim']) {
                            $indicator .= ($data['data_inicio'] ? ' ' : '').'até '.Carbon::parse($data['data_fim'])->format('d/m/Y');
                        }

                        return $indicator;
                    }),
                Filter::make('hora_inicio')
                    ->form([
                        TimePicker::make('hora_inicio')
                            ->label('Hora Início')
                            ->native(false)
                            ->seconds(false),
                    ])
                    ->query(fn ($query, array $data) => $query->when($data['hora_inicio'], fn ($q) => $q->where('hora_inicio', 'like', Carbon::parse($data['hora_inicio'])->format('H:i').'%'))),
                Filter::make('hora_fim')
                    ->form([
                        TimePicker::make('hora_fim')
                            ->label('Hora Fim')
                            ->native(false)
                            ->seconds(false),
                    ])
                    ->query(fn ($query, array $data) => $query->when($data['hora_fim'], fn ($q) => $q->where('hora_fim', 'like', Carbon::parse($data['hora_fim'])->format('H:i').'%'))),

                Filter::make('frequencias_pendentes')
                    ->label('Frequência Pendente')
                    ->indicator('Apenas Pendentes')
                    ->default()
                    ->query(fn ($query) => $query->whereRaw('
                        (SELECT COUNT(*) FROM matricula WHERE matricula.turma_id = cronograma_aula.turma_id) > 
                        (SELECT COUNT(*) FROM frequencia_escolar WHERE frequencia_escolar.cronograma_aula_id = cronograma_aula.id AND frequencia_escolar.situacao IS NOT NULL)
                    ')),
            ])
            ->actions([
                Action::make('notificarProfessorManual')
                    ->label('Notificar Professor')
                    ->tooltip('Enviar e-mail de pendência para o Professor')
                    ->icon('heroicon-o-envelope')
                    ->color('warning')
                    ->visible(fn ($record): bool => auth()->user()->can('notificarProfessorManual', $record) && $record->data->isPast() && $record->hasPendingFrequencies())
                    ->action(function ($record) {
                        $professor = $record->professor;
                        $user = $professor?->users->first();

                        if (! $user?->email) {
                            Notification::make()
                                ->title('Erro ao enviar e-mail')
                                ->body('O professor associado não possui um usuário com e-mail cadastrado.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $user->notify(new FrequenciaPendenteNotification($record));

                        Notification::make()
                            ->title('E-mail enviado com sucesso!')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
                Action::make('lancarFrequencia')
                    ->label('Frequência')
                    ->tooltip('Lançar Frequência')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->url(fn ($record): string => CronogramaAulaResource::getUrl('lancar-frequencia', ['record' => $record]))
                    ->visible(fn ($record): bool => auth()->user()->can('lancarFrequencia', $record))
                    ->badge(function ($record) {
                        $totalMatriculados = $record->turma->matriculas()->count();
                        $frequenciasLancadas = $record->frequencias()->whereNotNull('situacao')->count();
                        $faltando = $totalMatriculados - $frequenciasLancadas;

                        return $faltando > 0 ? (string) $faltando : null;
                    })
                    ->badgeColor('danger'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('bulkEdit')
                        ->label('Editar em Lote')
                        ->icon('heroicon-o-pencil-square')
                        ->form([
                            Select::make('periodo_letivo_id')
                                ->label('Período Letivo')
                                ->relationship('periodoLetivo', 'nome')
                                ->searchable()
                                ->preload(),
                            Select::make('turma_id')
                                ->label('Turma')
                                ->relationship('turma', 'nome')
                                ->searchable()
                                ->preload(),
                            Select::make('disciplina_id')
                                ->label('Disciplina')
                                ->relationship('disciplina', 'nome')
                                ->searchable()
                                ->preload(),
                            Select::make('pessoa_id')
                                ->label('Professor')
                                ->relationship('professor', 'nome')
                                ->searchable()
                                ->preload()
                                ->hidden(fn () => auth()->user()?->hasRole('professor')),
                            DatePicker::make('data')
                                ->native(false)
                                ->displayFormat('d/m/Y'),
                            TimePicker::make('hora_inicio')
                                ->label('Hora de Início')
                                ->native(false)
                                ->seconds(false),
                            TimePicker::make('hora_fim')
                                ->label('Hora de Fim')
                                ->native(false)
                                ->seconds(false),
                            Textarea::make('conteudo_ministrado')
                                ->label('Conteúdo Ministrado')
                                ->rows(3),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $updateData = array_filter($data, fn ($value) => filled($value));
                            if (empty($updateData)) {
                                return;
                            }
                            $records->each(fn ($record) => $record->update($updateData));
                        })
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn () => auth()->user()->can('update', CronogramaAula::class)),
                    BulkAction::make('bulkLancarFrequencia')
                        ->label('Lançar Frequência em Lote')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->form(function (Collection $records) {
                            $turmaIds = $records->pluck('turma_id')->unique();
                            $matriculas = Matricula::whereIn('turma_id', $turmaIds)
                                ->with('pessoa')
                                ->get()
                                ->unique('id')
                                ->sortBy('pessoa.nome');

                            return [
                                Repeater::make('frequencias')
                                    ->label('Frequência dos Alunos')
                                    ->schema([
                                        Select::make('matricula_id')
                                            ->label('Aluno')
                                            ->options($matriculas->mapWithKeys(fn ($m) => [
                                                $m->id => "{$m->periodoLetivo?->nome} - {$m->turma?->nome} - {$m->pessoa?->nome}",
                                            ]))
                                            ->required()
                                            ->disabled()
                                            ->dehydrated(),
                                        ToggleButtons::make('situacao')
                                            ->label('Situação')
                                            ->options([
                                                'presente' => 'Presente',
                                                'ausente' => 'Ausente',
                                            ])
                                            ->colors([
                                                'presente' => 'success',
                                                'ausente' => 'danger',
                                            ])
                                            ->icons([
                                                'presente' => 'heroicon-o-check',
                                                'ausente' => 'heroicon-o-x-circle',
                                            ])
                                            ->required()
                                            ->inline(),
                                    ])
                                    ->columns(2)
                                    ->addable(false)
                                    ->deletable(false)
                                    ->reorderable(false)
                                    ->default($matriculas->map(fn ($m) => [
                                        'matricula_id' => $m->id,
                                        'situacao' => 'presente',
                                    ])->toArray()),
                            ];
                        })
                        ->action(function (Collection $records, array $data): void {
                            foreach ($records as $record) {
                                // Pega apenas as matrículas que pertencem à turma deste cronograma
                                $matriculasDaTurma = Matricula::where('turma_id', $record->turma_id)->pluck('id')->toArray();

                                foreach ($data['frequencias'] as $frequenciaData) {
                                    if (in_array($frequenciaData['matricula_id'], $matriculasDaTurma)) {
                                        $record->frequencias()->updateOrCreate(
                                            ['matricula_id' => $frequenciaData['matricula_id']],
                                            ['situacao' => $frequenciaData['situacao']]
                                        );
                                    }
                                }
                            }

                            Notification::make()
                                ->title('Frequências lançadas com sucesso para '.$records->count().' aulas!')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn () => auth()->user()->can('lancarFrequencia', CronogramaAula::class)),
                    BulkAction::make('clonar')
                        ->label('Clonar Selecionadas')
                        ->icon('heroicon-o-document-duplicate')
                        ->form([
                            DatePicker::make('data')
                                ->label('Nova Data')
                                ->native(false)
                                ->displayFormat('d/m/Y'),
                            Select::make('periodo_letivo_id')
                                ->label('Período Letivo')
                                ->relationship('periodoLetivo', 'nome')
                                ->searchable()
                                ->preload(),
                            Select::make('turma_id')
                                ->label('Turma')
                                ->relationship('turma', 'nome')
                                ->searchable()
                                ->preload(),
                            Select::make('disciplina_id')
                                ->label('Disciplina')
                                ->relationship('disciplina', 'nome')
                                ->searchable()
                                ->preload(),
                            Select::make('pessoa_id')
                                ->label('Professor')
                                ->relationship('professor', 'nome')
                                ->searchable()
                                ->preload(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $clonedData = array_filter($data, fn ($value) => filled($value));
                            foreach ($records as $record) {
                                $clone = $record->replicate();
                                $clone->fill($clonedData);
                                $clone->save();
                            }

                            Notification::make()
                                ->title($records->count().' aulas clonadas com sucesso!')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn () => auth()->user()->can('clonar', CronogramaAula::class)),
                    BulkAction::make('bulkNotificarProfessores')
                        ->label('Notificar Professores (Pendências)')
                        ->icon('heroicon-o-envelope')
                        ->color('warning')
                        ->action(function (Collection $records): void {
                            $enviados = 0;
                            $falhas_email = 0;
                            $erros_futuro = 0;
                            $erros_sem_pendencia = 0;

                            foreach ($records as $record) {
                                if (! $record->data->isPast()) {
                                    $erros_futuro++;

                                    continue;
                                }

                                if (! $record->hasPendingFrequencies()) {
                                    $erros_sem_pendencia++;

                                    continue;
                                }

                                $professor = $record->professor;
                                $user = $professor?->users->first();

                                if ($user?->email) {
                                    $user->notify(new FrequenciaPendenteNotification($record));
                                    $enviados++;
                                } else {
                                    $falhas_email++;
                                }
                            }

                            if ($enviados > 0) {
                                Notification::make()
                                    ->title('Notificações enviadas')
                                    ->body("{$enviados} e-mails enviados com sucesso.")
                                    ->success()
                                    ->send();
                            }

                            if ($erros_futuro > 0 || $falhas_email > 0 || $erros_sem_pendencia > 0) {
                                $errorBody = '';
                                if ($erros_futuro > 0) {
                                    $errorBody .= "{$erros_futuro} aulas futuras não podem ser notificadas. ";
                                }
                                if ($erros_sem_pendencia > 0) {
                                    $errorBody .= "{$erros_sem_pendencia} aulas sem pendência de frequência ignoradas. ";
                                }
                                if ($falhas_email > 0) {
                                    $errorBody .= "{$falhas_email} professores não possuem e-mail cadastrado.";
                                }

                                Notification::make()
                                    ->title('Ocorreram pendências no processamento')
                                    ->body($errorBody)
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn () => auth()->user()->can('NotificarProfessorManual:CronogramaAula')),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }
}
