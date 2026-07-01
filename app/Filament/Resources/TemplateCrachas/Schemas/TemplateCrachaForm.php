<?php

namespace App\Filament\Resources\TemplateCrachas\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TemplateCrachaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Configurações do Crachá')
                    ->schema([
                        TextInput::make('nome')
                            ->label('Nome do Template')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('largura')
                            ->label('Largura (px)')
                            ->required()
                            ->numeric()
                            ->default(300)
                            ->minValue(100)
                            ->maxValue(1000)
                            ->live(),
                        TextInput::make('altura')
                            ->label('Altura (px)')
                            ->required()
                            ->numeric()
                            ->default(480)
                            ->minValue(100)
                            ->maxValue(1000)
                            ->live(),
                    ])->columns(3)
                    ->columnSpanFull(),

                Section::make('Editor de Layout')
                    ->schema([
                        ViewField::make('dados_layout')
                            ->label('')
                            ->view('filament.forms.components.cracha-editor')
                            ->columnSpanFull(),
                    ])->columnSpanFull(),
            ]);
    }
}
