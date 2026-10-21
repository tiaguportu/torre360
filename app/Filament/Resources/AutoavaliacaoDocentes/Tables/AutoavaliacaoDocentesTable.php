<?php

declare(strict_types=1);

namespace App\Filament\Resources\AutoavaliacaoDocentes\Tables;

use App\Enums\StatusAutoavaliacaoDocente;
use App\Exceptions\DesenvolvimentoDocenteException;
use App\Models\AutoavaliacaoDocente;
use App\Services\AutoavaliacaoDocenteService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AutoavaliacaoDocentesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('funcionario.pessoa.nome')->label('Professor')->searchable()->weight('bold'),
                TextColumn::make('ciclo.nome')->label('Ciclo')->sortable(),
                TextColumn::make('status')->label('Situação')->badge(),
                TextColumn::make('pontuacao_percentual')->label('Pontuação')->suffix('%')->numeric(1)->placeholder('—')->sortable(),
                TextColumn::make('enviada_em')->label('Enviada em')->dateTime('d/m/Y H:i')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('ciclo_id')->label('Ciclo')->relationship('ciclo', 'nome')->searchable()->preload(),
                SelectFilter::make('status')->label('Situação')->options(StatusAutoavaliacaoDocente::class),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Preencher')
                    ->visible(fn (AutoavaliacaoDocente $record): bool => (bool) auth()->user()?->can('update', $record)),
                ViewAction::make()
                    ->label('Abrir')
                    ->visible(fn (AutoavaliacaoDocente $record): bool => $record->estaEnviada() && (bool) auth()->user()?->can('view', $record)),
                Action::make('reabrir')
                    ->label('Reabrir')
                    ->icon('heroicon-o-lock-open')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('A autoavaliação volta ao professor para correção e a pontuação é zerada.')
                    ->visible(fn (AutoavaliacaoDocente $record): bool => (bool) auth()->user()?->can('reabrir', $record))
                    ->action(function (AutoavaliacaoDocente $record): void {
                        try {
                            app(AutoavaliacaoDocenteService::class)->reabrir($record, auth()->user());
                        } catch (DesenvolvimentoDocenteException $e) {
                            Notification::make()->title('Não foi possível reabrir')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Autoavaliação reaberta')->success()->send();
                    }),
            ])
            ->stackedOnMobile();
    }
}
