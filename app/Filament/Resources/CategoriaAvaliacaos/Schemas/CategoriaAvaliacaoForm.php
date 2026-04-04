<?php

namespace App\Filament\Resources\CategoriaAvaliacaos\Schemas;

use App\Models\CategoriaAvaliacao;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CategoriaAvaliacaoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nome')
                    ->required(),
                Select::make('categoria_avaliacao_substituicao_id')
                    ->label('Substitui a categoria')
                    ->helperText('Quando há avaliações desta categoria, a nota da categoria substituída é ignorada no cálculo do boletim.')
                    ->options(fn (?CategoriaAvaliacao $record) => CategoriaAvaliacao::query()
                        ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                        ->pluck('nome', 'id'))
                    ->searchable()
                    ->nullable()
                    ->placeholder('Nenhuma (não substitui)'),
            ]);
    }
}
