<?php

namespace App\Filament\Resources\Livros\Tables;

use App\Models\Livro;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class LivrosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('capa')
                    ->label('Capa')
                    ->disk('public')
                    ->visibility('public')
                    ->square()
                    ->defaultImageUrl(fn () => 'https://ui-avatars.com/api/?name=Livro&background=e2e8f0&color=64748b')
                    ->toggleable(),
                TextColumn::make('codigo')
                    ->label('Tombo')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('titulo')
                    ->label('Título')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('autor')
                    ->label('Autor')
                    ->searchable(),
                TextColumn::make('faixa_etaria')
                    ->label('Faixa Etária')
                    ->badge()
                    ->color('info')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('editora')
                    ->label('Editora')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('categoria')
                    ->label('Categoria')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('isbn')
                    ->label('ISBN')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('quantidade_disponivel')
                    ->label('Disponíveis')
                    ->suffix(fn (Livro $record) => ' / '.$record->quantidade_total)
                    ->color(fn (Livro $record) => $record->quantidade_disponivel > 0 ? 'success' : 'danger')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('faixa_etaria')
                    ->label('Faixa Etária')
                    ->options([
                        'Livre' => 'Livre',
                        '0 a 3 anos' => '0 a 3 anos',
                        '4 a 6 anos' => '4 a 6 anos',
                        '7 a 9 anos' => '7 a 9 anos',
                        '10 a 12 anos' => '10 a 12 anos',
                        '13 a 15 anos' => '13 a 15 anos',
                        '16+ anos' => '16+ anos',
                    ]),
            ])
            ->recordActions([
                Action::make('imprimirEtiqueta')
                    ->label('Etiqueta')
                    ->icon('heroicon-o-qr-code')
                    ->color('gray')
                    ->url(fn (Livro $record): string => route('biblioteca.etiquetas.imprimir', ['livros' => $record->id, 'autoprint' => 1]))
                    ->openUrlInNewTab(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('imprimirEtiquetas')
                        ->label('Imprimir Etiquetas em Lote')
                        ->icon('heroicon-o-printer')
                        ->color('primary')
                        ->action(function (Collection $records) {
                            $ids = $records->pluck('id')->join(',');

                            return redirect()->away(route('biblioteca.etiquetas.imprimir', ['livros' => $ids, 'autoprint' => 1]));
                        }),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('titulo')
            ->stackedOnMobile();
    }
}
