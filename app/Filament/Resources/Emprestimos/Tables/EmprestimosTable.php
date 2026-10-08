<?php

namespace App\Filament\Resources\Emprestimos\Tables;

use App\Enums\StatusEmprestimo;
use App\Models\Emprestimo;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EmprestimosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('livro.titulo')
                    ->label('Livro')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('matricula.pessoa.nome')
                    ->label('Aluno')
                    ->searchable(),
                TextColumn::make('data_emprestimo')
                    ->label('Empréstimo')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('data_prevista_devolucao')
                    ->label('Devolução Prevista')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn (Emprestimo $record) => $record->status === StatusEmprestimo::Atrasado ? 'danger' : null),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(StatusEmprestimo::class),
            ])
            ->recordActions([
                self::devolverAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalDescription('Os empréstimos selecionados e seu histórico serão excluídos. Os que ainda estão em aberto devolvem o exemplar ao acervo disponível.'),
                ]),
            ])
            ->defaultSort('data_prevista_devolucao')
            ->stackedOnMobile();
    }

    public static function devolverAction(): Action
    {
        return Action::make('devolver')
            ->label('Registrar Devolução')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (Emprestimo $record): bool => $record->status !== StatusEmprestimo::Devolvido)
            ->requiresConfirmation()
            ->modalDescription(fn (Emprestimo $record): string => "Confirma a devolução de \"{$record->livro->titulo}\"?")
            ->action(function (Emprestimo $record): void {
                // false = já estava devolvido (outra aba ou duplo clique): o exemplar não é devolvido duas vezes.
                if (! $record->registrarDevolucao()) {
                    Notification::make()
                        ->title('Este empréstimo já havia sido devolvido')
                        ->body('Nenhuma alteração foi feita no estoque.')
                        ->warning()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Devolução registrada com sucesso!')
                    ->success()
                    ->send();
            });
    }
}
