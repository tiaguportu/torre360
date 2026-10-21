<?php

declare(strict_types=1);

namespace App\Filament\Resources\AvaliacaoDocentes\Tables;

use App\Enums\StatusAvaliacaoDocente;
use App\Exceptions\AvaliacaoDocenteException;
use App\Filament\Resources\PlanoDesenvolvimentoDocentes\PlanoDesenvolvimentoDocenteResource;
use App\Models\AvaliacaoDocente;
use App\Services\AvaliacaoDocenteService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AvaliacaoDocentesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('data_observacao', 'desc')
            ->columns([
                TextColumn::make('funcionario.pessoa.nome')
                    ->label('Professor')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('ciclo.nome')
                    ->label('Ciclo')
                    ->sortable(),
                TextColumn::make('data_observacao')
                    ->label('Observação')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('avaliador.name')
                    ->label('Avaliador')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('pontuacao_percentual')
                    ->label('Pontuação')
                    ->suffix('%')
                    ->numeric(1)
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Situação')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('ciclo_id')
                    ->label('Ciclo')
                    ->relationship('ciclo', 'nome')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->label('Situação')
                    ->options(StatusAvaliacaoDocente::class),
            ])
            ->recordActions([
                ViewAction::make()->label('Abrir'),
                PlanoDesenvolvimentoDocenteResource::acaoPlanoDaAvaliacao(),
                Action::make('reabrir')
                    ->label('Reabrir')
                    ->icon('heroicon-o-lock-open')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('A avaliação volta a rascunho, a pontuação é zerada e ela precisará ser finalizada de novo.')
                    ->visible(fn (AvaliacaoDocente $record): bool => (bool) auth()->user()?->can('reabrir', $record))
                    ->action(function (AvaliacaoDocente $record): void {
                        try {
                            app(AvaliacaoDocenteService::class)->reabrir($record, auth()->user());
                        } catch (AvaliacaoDocenteException $e) {
                            Notification::make()->title('Não foi possível reabrir')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Avaliação reaberta para correção')->success()->send();
                    }),
            ])
            ->stackedOnMobile();
    }
}
