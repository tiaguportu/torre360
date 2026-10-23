<?php

declare(strict_types=1);

namespace App\Filament\Resources\CasosFrequencia\Tables;

use App\Enums\StatusCasoFrequencia;
use App\Filament\Resources\CasosFrequencia\CasoFrequenciaResource;
use App\Models\CasoFrequencia;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CasosFrequenciaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('matricula.pessoa.nome')
                    ->label('Aluno')
                    ->description(fn (CasoFrequencia $record): ?string => $record->matricula?->turma?->nome)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Situação')
                    ->badge(),
                TextColumn::make('faltas')
                    ->label('Faltas')
                    ->state(fn (CasoFrequencia $record): string => "{$record->faltas} de {$record->aulas_previstas} aulas")
                    ->description(fn (CasoFrequencia $record): string => number_format((float) $record->percentual_faltas, 1, ',', '.').'% · limite '.$record->limite_aulas)
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy('faltas', $direction)),
                TextColumn::make('contatos_count')
                    ->label('Contatos')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'danger'),
                TextColumn::make('conselhoTutelar.nome')
                    ->label('Conselho Tutelar')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('notificado_em')
                    ->label('Ofício enviado em')
                    ->dateTime('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Aberto em')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Situação')
                    ->options(StatusCasoFrequencia::class)
                    ->multiple(),
            ])
            ->recordActions([
                ViewAction::make()->label('Abrir'),
            ])
            ->recordUrl(fn (CasoFrequencia $record): string => CasoFrequenciaResource::getUrl('view', ['record' => $record]))
            ->defaultSort('created_at', 'desc')
            ->stackedOnMobile();
    }
}
