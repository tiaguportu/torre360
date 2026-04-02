<?php

namespace App\Filament\Resources\CronogramaAulas\Tables;

use App\Filament\Resources\CronogramaAulas\CronogramaAulaResource;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

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
                Filter::make('data')
                    ->form([
                        DatePicker::make('data')
                            ->label('Data')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ])
                    ->query(fn ($query, array $data) => $query->when($data['data'], fn ($q) => $q->whereDate('data', $data['data']))),
                Filter::make('hora_inicio')
                    ->form([
                        TimePicker::make('hora_inicio')
                            ->label('Hora Início')
                            ->native(false)
                            ->seconds(false),
                    ])
                    ->query(fn ($query, array $data) => $query->when($data['hora_inicio'], fn ($q) => $q->where('hora_inicio', 'like', $data['hora_inicio'].'%'))),
                Filter::make('hora_fim')
                    ->form([
                        TimePicker::make('hora_fim')
                            ->label('Hora Fim')
                            ->native(false)
                            ->seconds(false),
                    ])
                    ->query(fn ($query, array $data) => $query->when($data['hora_fim'], fn ($q) => $q->where('hora_fim', 'like', $data['hora_fim'].'%'))),

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
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->toolbarActions([
                Action::make('calendar')
                    ->label('Visualizar Calendário')
                    ->icon('heroicon-o-calendar')
                    ->url(fn (): string => CronogramaAulaResource::getUrl('calendar')),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
