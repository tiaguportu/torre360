<?php

namespace App\Filament\Resources\ReposicaoAulas\Tables;

use App\Models\Disciplina;
use App\Models\ReposicaoAula;
use App\Models\Turma;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReposicaoAulasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('turma.nome')
                    ->label('Turma')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('disciplina.nome')
                    ->label('Disciplina')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('professor.nome')
                    ->label('Professor')
                    ->placeholder('Não atribuído')
                    ->toggleable(),

                TextColumn::make('data_reposicao')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('hora_inicio')
                    ->label('Horário')
                    ->formatStateUsing(fn (ReposicaoAula $record): string => substr((string) $record->hora_inicio, 0, 5).' às '.substr((string) $record->hora_fim, 0, 5)),

                TextColumn::make('motivo')
                    ->label('Motivo')
                    ->limit(35)
                    ->tooltip(fn (ReposicaoAula $record): string => $record->motivo),

                TextColumn::make('status')
                    ->label('Situação')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'realizada' => 'success',
                        'agendada' => 'warning',
                        'cancelada' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'realizada' => 'Realizada',
                        'agendada' => 'Agendada',
                        'cancelada' => 'Cancelada',
                        default => $state,
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Situação')
                    ->options([
                        'agendada' => 'Agendada',
                        'realizada' => 'Realizada',
                        'cancelada' => 'Cancelada',
                    ]),

                SelectFilter::make('turma_id')
                    ->label('Turma')
                    ->options(Turma::pluck('nome', 'id')),

                SelectFilter::make('disciplina_id')
                    ->label('Disciplina')
                    ->options(Disciplina::pluck('nome', 'id')),
            ])
            ->actions([
                Action::make('marcarRealizada')
                    ->label('Concluir')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (ReposicaoAula $record): bool => $record->status === 'agendada')
                    ->action(fn (ReposicaoAula $record) => $record->update(['status' => 'realizada'])),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->stackedOnMobile();
    }
}
