<?php

namespace App\Filament\Resources\Livros\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LivroForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Livro')
                    ->columns(2)
                    ->schema([
                        TextInput::make('titulo')
                            ->label('Título')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('autor')
                            ->label('Autor')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('editora')
                            ->label('Editora')
                            ->maxLength(255),
                        TextInput::make('isbn')
                            ->label('ISBN')
                            ->maxLength(255),
                        TextInput::make('categoria')
                            ->label('Categoria')
                            ->maxLength(255),
                        TextInput::make('quantidade_total')
                            ->label('Quantidade Total de Exemplares')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->required(),
                        TextInput::make('quantidade_disponivel')
                            ->label('Quantidade Disponível')
                            ->numeric()
                            ->minValue(0)
                            ->default(1)
                            ->required()
                            ->helperText('Diminui a cada empréstimo e volta ao normal quando o livro é devolvido.'),
                    ]),
            ]);
    }
}
