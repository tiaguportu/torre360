<?php

namespace App\Filament\Resources\FrequenciaEscolars\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class FrequenciaEscolarsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('matricula')
                    ->label('Aluno')
                    ->state(fn ($record) => "{$record->matricula->pessoa->nome} - {$record->matricula->codigo}")
                    ->searchable(['matricula.codigo', 'matricula.pessoa.nome']),
                TextColumn::make('cronogramaAula')
                    ->label('Aula')
                    ->state(function ($record) {
                        $ca = $record->cronogramaAula;
                        if (! $ca) {
                            return 'N/A';
                        }
                        $data = Carbon::parse($ca->data)->format('d/m/Y');
                        $inicio = Carbon::parse($ca->hora_inicio)->format('H:i');
                        $fim = Carbon::parse($ca->hora_fim)->format('H:i');
                        $turma = $ca->turma?->nome ?? 'N/A';
                        $disciplina = $ca->disciplina?->nome ?? 'N/A';

                        return "{$data} ({$inicio}-{$fim}) - {$turma} - {$disciplina}";
                    })
                    ->searchable(['cronogramaAula.data', 'cronogramaAula.turma.nome', 'cronogramaAula.disciplina.nome']),
                TextColumn::make('situacao')
                    ->searchable(),
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
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
