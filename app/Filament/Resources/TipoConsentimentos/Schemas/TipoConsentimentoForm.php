<?php

namespace App\Filament\Resources\TipoConsentimentos\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class TipoConsentimentoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tipo de Consentimento')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nome')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('texto_padrao')
                            ->label('Texto do Consentimento')
                            ->helperText('Texto exibido para a família no Portal antes de autorizar ou não.')
                            ->rows(4)
                            ->required()
                            ->columnSpanFull(),
                        Toggle::make('exige_renovacao_periodica')
                            ->label('Exige Renovação Periódica')
                            ->live(),
                        TextInput::make('periodicidade_meses')
                            ->label('Periodicidade (meses)')
                            ->numeric()
                            ->minValue(1)
                            ->default(12)
                            ->visible(fn (Get $get): bool => (bool) $get('exige_renovacao_periodica'))
                            ->required(fn (Get $get): bool => (bool) $get('exige_renovacao_periodica')),
                        Toggle::make('is_ativo')
                            ->label('Ativo')
                            ->default(true)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
