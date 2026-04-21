<?php

namespace App\Filament\Resources\TemplateRelatorioPreceptorias\Schemas;

use AmidEsfahani\FilamentTinyEditor\TinyEditor;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TemplateRelatorioPreceptoriaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informações do Template')
                    ->schema([
                        TextInput::make('nome')
                            ->label('Nome do Template')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->columnSpanFull(),

                Section::make('Corpo do Template')
                    ->schema([
                        TinyEditor::make('corpo')
                            ->label('Conteúdo')
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
