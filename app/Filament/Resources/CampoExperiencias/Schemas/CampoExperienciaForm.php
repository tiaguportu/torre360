<?php

namespace App\Filament\Resources\CampoExperiencias\Schemas;

use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class CampoExperienciaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informações do Campo de Experiência')
                    ->description('Defina o nome e a descrição do campo de experiência conforme a BNCC.')
                    ->schema([
                        TextInput::make('nome')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ex: O eu, o outro e o nós'),
                        Textarea::make('descricao')
                            ->label('Descrição')
                            ->rows(3)
                            ->placeholder('Detalhamento do campo de experiência...'),
                    ]),
            ]);
    }
}
