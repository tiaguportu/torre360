<?php

namespace App\Filament\Resources\CategoriaAvaliacaos\Schemas;

use App\Models\CategoriaAvaliacao;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                TextInput::make('ordem_boletim')
                    ->label('Ordem no Boletim')
                    ->numeric()
                    ->helperText('Define a ordem de exibição desta categoria no boletim.')
                    ->default(0),

                Toggle::make('eh_recuperacao')
                    ->label('É recuperação final?')
                    ->helperText('No fechamento do ciclo letivo, a nota consolidada desta categoria substitui a menor média de etapa do aluno na disciplina, desde que seja melhor que ela.')
                    ->default(false),

                Select::make('substituidas')
                    ->label('Substitui quais categorias?')
                    ->helperText('Uma nota nesta categoria substituirá a menor nota entre as categorias selecionadas acima.')
                    ->relationship(
                        'substituidas',
                        'nome',
                        modifyQueryUsing: fn ($query, ?CategoriaAvaliacao $record) => $query
                            ->when($record, fn ($q) => $q->where('categoria_avaliacao.id', '!=', $record->id))
                            ->orderBy('ordem_boletim')
                    )
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->nome_com_descricao)
                    ->searchable(['nome', 'descricao'])
                    ->multiple()
                    ->preload()
                    ->placeholder('Nenhuma (não substitui)'),
            ]);
    }
}
