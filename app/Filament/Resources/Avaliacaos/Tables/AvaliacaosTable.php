<?php

namespace App\Filament\Resources\Avaliacaos\Tables;

use App\Filament\Resources\Avaliacaos\AvaliacaoResource;
use App\Filament\Resources\Avaliacaos\Schemas\AvaliacaoForm;
use App\Models\Avaliacao;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Tables\Columns\TextColumn;
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
                    ->toggleable(),

                TextColumn::make('data_prevista')
                    ->label('Data Prevista')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('data_ocorrencia')
                    ->label('Data Ocorrência')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('data_limite_lancamento')
                    ->label('Limite Lançamento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('nota_maxima')
                    ->label('MÁX')
                    ->numeric(2)
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('peso_etapa_avaliativa')
                    ->label('Peso')
                    ->numeric(2)
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('lancar_notas')
                    ->label('Lançar Notas')
                    ->icon('heroicon-o-academic-cap')
                    ->color('success')
                    ->badge(fn (Avaliacao $record): ?int => ($count = (int) $record->matriculas_count - (int) $record->notas_count) > 0 ? $count : null)
                    ->badgeColor('danger')
                    ->url(fn (Avaliacao $record): string => AvaliacaoResource::getUrl('lancar-notas', ['record' => $record])),
                ReplicateAction::make()
                    ->before(fn (Avaliacao $record) => $record->data_prevista = now())
                    ->label('Clonar')
                    ->icon('heroicon-o-document-duplicate'),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('bulk_replicate')
                        ->label('Clonar Selecionados')
                        ->icon('heroicon-o-document-duplicate')
                        ->action(fn (Collection $records) => $records->each(function ($record) {
                            $clone = $record->replicate();
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
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
