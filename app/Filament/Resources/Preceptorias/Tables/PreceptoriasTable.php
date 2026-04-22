<?php

namespace App\Filament\Resources\Preceptorias\Tables;

use App\Models\Preceptoria;
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
                    ->state(fn ($record) => $record->relatorio !== null)
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
                Filter::make('sem_relatorio')
                    ->label('Sem Relatório')
                    ->query(fn (Builder $query) => $query->doesntHave('relatorio')),
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
                            Select::make('professor_id')
                                ->label('Novo Professor(a) (Opcional)')
                                ->placeholder('Manter professor original')
                                ->relationship('professor', 'nome')
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
                            TimePicker::make('hora_inicio')
                                ->label('Hora Início')
                                ->seconds(false),
                            TimePicker::make('hora_fim')
                                ->label('Hora Fim')
                                ->seconds(false),
                            Select::make('professor_id')
                                ->label('Professor(a)')
                                ->relationship('professor', 'nome')
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
