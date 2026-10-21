<?php

declare(strict_types=1);

namespace App\Filament\Resources\Cardapios\Tables;

use App\Enums\Alergeno;
use App\Enums\TipoRefeicaoCardapio;
use App\Models\Cardapio;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CardapiosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('unidade'))
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('data')->orderBy('tipo_refeicao'))
            ->columns([
                TextColumn::make('data')
                    ->label('Data')
                    ->date('d/m/Y (D)')
                    ->sortable(),
                TextColumn::make('tipo_refeicao')
                    ->label('Refeição')
                    ->badge(),
                TextColumn::make('descricao')
                    ->label('Cardápio')
                    ->limit(60)
                    ->tooltip(fn (Cardapio $record): string => (string) $record->descricao)
                    ->searchable(),
                TextColumn::make('alergenos')
                    ->label('Contém')
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn ($state): string => Alergeno::tryFrom((string) $state)?->getLabel() ?? (string) $state)
                    ->placeholder('—'),
                TextColumn::make('unidade.nome')
                    ->label('Unidade')
                    ->placeholder('Todas')
                    ->toggleable(),
                IconColumn::make('publicado')
                    ->label('Publicado')
                    ->boolean(),
            ])
            ->filters([
                Filter::make('periodo')
                    ->label('Período')
                    ->schema([
                        DatePicker::make('de')->label('De')->native(false)->displayFormat('d/m/Y')->default(fn (): string => now()->startOfWeek()->toDateString()),
                        DatePicker::make('ate')->label('Até')->native(false)->displayFormat('d/m/Y')->default(fn (): string => now()->addWeeks(2)->endOfWeek()->toDateString()),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['de'] ?? null, fn (Builder $q, $de) => $q->whereDate('data', '>=', $de))
                        ->when($data['ate'] ?? null, fn (Builder $q, $ate) => $q->whereDate('data', '<=', $ate))),
                SelectFilter::make('tipo_refeicao')->label('Refeição')->options(TipoRefeicaoCardapio::class),
                SelectFilter::make('unidade_id')->label('Unidade')->relationship('unidade', 'nome')->searchable()->preload(),
                TernaryFilter::make('publicado')->label('Publicação')->trueLabel('Publicados')->falseLabel('Rascunhos'),
            ])
            ->recordActions([
                Action::make('alternarPublicacao')
                    ->label(fn (Cardapio $record): string => $record->publicado ? 'Despublicar' : 'Publicar')
                    ->icon(fn (Cardapio $record): string => $record->publicado ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn (Cardapio $record): string => $record->publicado ? 'gray' : 'success')
                    ->requiresConfirmation()
                    ->visible(fn (Cardapio $record): bool => (bool) auth()->user()?->can('publicar', $record))
                    ->action(fn (Cardapio $record) => $record->update(['publicado' => ! $record->publicado])),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->stackedOnMobile();
    }
}
