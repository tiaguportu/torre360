<?php

namespace App\Filament\Resources\Unidades\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UnidadeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informações Gerais')
                    ->schema([
                        TextInput::make('nome')
                            ->required(),
                        TextInput::make('cnpj'),
                        Select::make('endereco_id')
                            ->relationship('endereco', 'logradouro')
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),
                        Toggle::make('flag_ativo')
                            ->required(),
                    ])->columns(2),

                Section::make('Redes Sociais e Contato')
                    ->description('Configurações exclusivas da unidade para site e e-mails.')
                    ->schema([
                        TextInput::make('celular_whatsapp')
                            ->label('Celular / WhatsApp')
                            ->placeholder('(00) 00000-0000'),
                        TextInput::make('instagram')
                            ->label('Instagram URL')
                            ->placeholder('https://instagram.com/unidade'),
                        TextInput::make('facebook')
                            ->label('Facebook URL')
                            ->placeholder('https://facebook.com/unidade'),
                        TextInput::make('youtube')
                            ->label('YouTube URL')
                            ->placeholder('https://youtube.com/c/unidade'),
                    ])->columns(2),
            ]);
    }
}
