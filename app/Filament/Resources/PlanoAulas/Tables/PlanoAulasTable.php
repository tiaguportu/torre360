<?php

namespace App\Filament\Resources\PlanoAulas\Tables;

use App\Models\PlanoAula;
use App\Services\PlanoAulaService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PlanoAulasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('data_prevista', 'desc')
            ->columns([
                TextColumn::make('data_prevista')
                    ->label('Data Prevista')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('turma.nome')
                    ->label('Turma')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('disciplina.nome')
                    ->label('Disciplina')
                    ->searchable(),
                TextColumn::make('professor.nome')
                    ->label('Professor')
                    ->placeholder('—'),
                TextColumn::make('objetivos')
                    ->label('Objetivos')
                    ->limit(50)
                    ->wrap(),
                IconColumn::make('executado_em')
                    ->label('Executado')
                    ->boolean()
                    ->getStateUsing(fn (PlanoAula $record): bool => $record->foiExecutado()),
            ])
            ->filters([
                SelectFilter::make('turma')
                    ->relationship('turma', 'nome')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('executado')
                    ->label('Executado')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('executado_em'),
                        false: fn ($query) => $query->whereNull('executado_em'),
                    ),
            ])
            ->actions([
                Action::make('executar')
                    ->label('Executar')
                    ->icon('heroicon-o-play-circle')
                    ->color('success')
                    ->visible(fn (PlanoAula $record): bool => ! $record->foiExecutado())
                    ->requiresConfirmation()
                    ->modalHeading('Executar Plano de Aula')
                    ->modalDescription('Cria o registro desta aula no diário (cronograma), com o conteúdo e as habilidades previstas. Depois de executado, o plano não pode mais ser editado.')
                    ->form([
                        DatePicker::make('data')
                            ->label('Data real da aula')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->default(fn (PlanoAula $record) => $record->data_prevista),
                    ])
                    ->action(function (array $data, PlanoAula $record) {
                        app(PlanoAulaService::class)->executar($record, $data['data'] ?? null);

                        Notification::make()
                            ->title('Plano executado! A aula foi registrada no diário.')
                            ->success()
                            ->send();
                    }),
                EditAction::make()
                    ->visible(fn (PlanoAula $record): bool => ! $record->foiExecutado()),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }
}
