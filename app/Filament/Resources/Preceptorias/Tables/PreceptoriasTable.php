<?php

namespace App\Filament\Resources\Preceptorias\Tables;

use App\Models\Preceptoria;
use Carbon\Carbon;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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

class PreceptoriasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('data')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),

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
                    ->state(fn ($record) => $record->relatorios()->exists())
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
                            auth()->user()?->hasRole('professor') && ! auth()->user()?->hasAnyRole(['super_admin', 'admin', 'secretaria']),
                            fn ($q) => $q->whereIn('id', auth()->user()?->pessoas->pluck('id'))
                        )
                        ->orderBy('nome')
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

            ->recordActions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('clone')
                        ->label('Clonar em Lote')
                        ->icon(Heroicon::OutlinedDocumentDuplicate)
                        ->color('info')
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
                                        auth()->user()?->hasRole('professor') && ! auth()->user()?->hasAnyRole(['super_admin', 'admin', 'secretaria']),
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

                    BulkAction::make('editar_lote')
                        ->label('Editar em Lote')
                        ->icon(Heroicon::OutlinedPencilSquare)
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
                                        auth()->user()?->hasRole('professor') && ! auth()->user()?->hasAnyRole(['super_admin', 'admin', 'secretaria']),
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
            ]);
    }
}
