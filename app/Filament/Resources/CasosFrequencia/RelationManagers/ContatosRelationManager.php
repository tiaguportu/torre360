<?php

declare(strict_types=1);

namespace App\Filament\Resources\CasosFrequencia\RelationManagers;

use App\Models\ContatoCasoFrequencia;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tentativas de contato com a família (Onda 57): a prova de que os recursos da escola foram esgotados antes do ofício. Só
 * leitura aqui; o registro é feito pela ação "Registrar contato" da página do caso.
 */
class ContatosRelationManager extends RelationManager
{
    protected static string $relationship = 'contatos';

    protected static ?string $title = 'Contatos com a família';

    protected static ?string $modelLabel = 'contato';

    protected static ?string $pluralModelLabel = 'contatos';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('data')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('registradoPor'))
            ->columns([
                TextColumn::make('data')->label('Data')->date('d/m/Y')->sortable(),
                TextColumn::make('meio')->label('Meio')->formatStateUsing(fn ($state): string => $state->getLabel()),
                TextColumn::make('resultado')->label('Resultado')->badge(),
                TextColumn::make('observacao')
                    ->label('O que a família disse')
                    ->limit(80)
                    ->tooltip(fn (ContatoCasoFrequencia $record): ?string => $record->observacao)
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('registradoPor.name')->label('Registrado por')->placeholder('—'),
            ])
            ->defaultSort('data', 'desc')
            ->paginated(false)
            ->stackedOnMobile();
    }
}
