<?php

namespace App\Filament\Resources\BemPatrimonials\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BemPatrimonialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Bem Patrimonial')
                    ->columns(2)
                    ->schema([
                        TextInput::make('descricao')
                            ->label('Descrição')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('numero_patrimonio')
                            ->label('Número de Patrimônio')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('categoria')
                            ->label('Categoria')
                            ->required()
                            ->datalist(['Informática', 'Mobiliário', 'Material Pedagógico', 'Laboratório', 'Outros'])
                            ->maxLength(255),
                        DatePicker::make('data_aquisicao')
                            ->label('Data de Aquisição')
                            ->native(false),
                        TextInput::make('valor_aquisicao')
                            ->label('Valor de Aquisição (R$)')
                            ->prefix('R$')
                            ->numeric()
                            ->minValue(0),
                        Select::make('unidade_id')
                            ->label('Unidade')
                            ->relationship('unidade', 'nome')
                            ->searchable()
                            ->preload(),
                        Select::make('sala_id')
                            ->label('Sala')
                            ->relationship('sala', 'nome')
                            ->searchable()
                            ->preload(),
                        Select::make('fornecedor_id')
                            ->label('Fornecedor')
                            ->relationship('fornecedor', 'razao_social')
                            ->searchable()
                            ->preload(),
                    ]),
            ]);
    }
}
