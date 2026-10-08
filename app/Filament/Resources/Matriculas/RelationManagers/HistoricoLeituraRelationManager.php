<?php

namespace App\Filament\Resources\Matriculas\RelationManagers;

use App\Enums\StatusEmprestimo;
use App\Models\Emprestimo;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HistoricoLeituraRelationManager extends RelationManager
{
    protected static string $relationship = 'emprestimos';

    protected static ?string $title = 'Histórico de Leitura (Biblioteca)';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-book-open';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                ImageColumn::make('livro.capa')
                    ->label('Capa')
                    ->disk('public')
                    ->square()
                    ->defaultImageUrl(fn () => 'https://ui-avatars.com/api/?name=Livro&background=e2e8f0&color=64748b'),
                TextColumn::make('livro.titulo')
                    ->label('Obra / Livro')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Emprestimo $record): string => $record->livro->autor ?? ''),
                TextColumn::make('livro.codigo')
                    ->label('Tombo')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('data_emprestimo')
                    ->label('Retirado em')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('data_prevista_devolucao')
                    ->label('Prazo')
                    ->date('d/m/Y')
                    ->color(fn (Emprestimo $record) => $record->status === StatusEmprestimo::Atrasado ? 'danger' : null)
                    ->sortable(),
                TextColumn::make('data_devolucao')
                    ->label('Devolvido em')
                    ->date('d/m/Y')
                    ->placeholder('Em leitura')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Situação')
                    ->badge(),
            ])
            ->recordActions([
                Action::make('devolver')
                    ->label('Devolver')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Emprestimo $record): bool => $record->status !== StatusEmprestimo::Devolvido)
                    ->requiresConfirmation()
                    ->action(function (Emprestimo $record): void {
                        $record->registrarDevolucao();

                        Notification::make()
                            ->title('Devolução registrada com sucesso!')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('data_emprestimo', 'desc')
            ->stackedOnMobile();
    }
}
