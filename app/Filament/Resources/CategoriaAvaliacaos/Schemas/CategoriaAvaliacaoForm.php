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
                    ->required()
                    ->maxLength(255),
                TextInput::make('descricao')
                    ->label('Descrição')
                    ->maxLength(255),
                TextInput::make('ordem')
                    ->label('Ordem')
                    ->numeric()
                    ->default(0)
                    ->required(),
                TextInput::make('ordem_boletim')
                    ->label('Ordem no Boletim')
                    ->numeric()
                    ->helperText('Define a ordem de exibição desta categoria no boletim.')
                    ->default(0),

                Select::make('categoria_avaliacao_substituicao_id')
                    ->label('Substitui a categoria')
                    ->helperText('Quando há avaliações desta categoria, a nota da categoria substituída é ignorada no cálculo do boletim.')
                    ->relationship(
                        'substituicao',
                        'nome',
                        modifyQueryUsing: fn ($query, ?CategoriaAvaliacao $record) => $query
                            ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                            ->orderBy('ordem')
                    )
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->nome} - {$record->descricao}")
                    ->searchable(['nome', 'descricao'])
                    ->nullable()
                    ->preload()
                    ->placeholder('Nenhuma (não substitui)'),
            ]);
    }
}
