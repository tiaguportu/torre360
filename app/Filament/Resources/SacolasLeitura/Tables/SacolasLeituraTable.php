<?php

namespace App\Filament\Resources\SacolasLeitura\Tables;

use App\Enums\StatusSacolaLeitura;
use App\Filament\Resources\SacolasLeitura\SacolaLeituraResource;
use App\Models\SacolaLeitura;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SacolasLeituraTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')
                    ->label('Tombo Sacola')
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('titulo')
                    ->label('Título da Sacola')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('turma.nome')
                    ->label('Turma')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Não vinculada'),

                TextColumn::make('responsavel.nome')
                    ->label('Professor(a) / Resp.')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('progresso_devolucao')
                    ->label('Livros na Sacola')
                    ->state(fn (SacolaLeitura $record): string => "{$record->totalDevolvidos()} / {$record->totalLivros()} devolvidos")
                    ->badge()
                    ->color(fn (SacolaLeitura $record): string => match (true) {
                        $record->totalLivros() === 0 => 'gray',
                        $record->totalDevolvidos() === $record->totalLivros() => 'success',
                        $record->totalDevolvidos() > 0 => 'warning',
                        default => 'primary',
                    }),

                TextColumn::make('data_retirada')
                    ->label('Retirada')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('data_prevista_devolucao')
                    ->label('Previsão Devolução')
                    ->date('d/m/Y')
                    ->color(fn (SacolaLeitura $record): ?string => $record->isAtrasada() ? 'danger' : null)
                    ->weight(fn (SacolaLeitura $record): ?string => $record->isAtrasada() ? 'bold' : null)
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Situação')
                    ->badge()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Situação')
                    ->options(StatusSacolaLeitura::class),

                SelectFilter::make('turma_id')
                    ->label('Filtrar por Turma')
                    ->relationship('turma', 'nome'),
            ])
            ->recordActions([
                Action::make('gerenciar')
                    ->label('Gerenciar / Bipar')
                    ->icon('heroicon-o-shopping-bag')
                    ->color('primary')
                    ->url(fn (SacolaLeitura $record): string => SacolaLeituraResource::getUrl('gerenciar', ['record' => $record])),

                Action::make('ficha')
                    ->label('Ficha de Controle')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (SacolaLeitura $record): string => route('biblioteca.sacolas.ficha', $record))
                    ->openUrlInNewTab(),

                Action::make('devolver_completa')
                    ->label('Devolver Sacola')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (SacolaLeitura $record): bool => $record->status !== StatusSacolaLeitura::Devolvida && $record->totalPendentes() > 0)
                    ->requiresConfirmation()
                    ->modalHeading('Devolver todos os livros da sacola?')
                    ->modalDescription(fn (SacolaLeitura $record): string => "Todos os {$record->totalPendentes()} livro(s) pendentes serão recolocados imediatamente no acervo disponível.")
                    ->action(function (SacolaLeitura $record): void {
                        $record->devolverTodosItens();

                        Notification::make()
                            ->title('Sacola devolvida com sucesso!')
                            ->body('Todos os livros foram devolvidos ao acervo da biblioteca.')
                            ->success()
                            ->send();
                    }),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('data_retirada', 'desc')
            ->stackedOnMobile();
    }
}
