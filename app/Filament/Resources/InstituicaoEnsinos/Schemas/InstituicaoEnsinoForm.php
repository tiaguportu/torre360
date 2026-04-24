<?php

namespace App\Filament\Resources\InstituicaoEnsinos\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InstituicaoEnsinoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informações Gerais')
                    ->schema([
                        TextInput::make('nome')
                            ->required(),
                        TextInput::make('cnpj')
                            ->mask('99.999.999/9999-99'),
                        FileUpload::make('logo')
                            ->image()
                            ->directory('instituicao-logos')
                            ->visibility('public'),
                        Select::make('endereco_id')
                            ->relationship('endereco', 'logradouro')
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),
                        Toggle::make('flag_ativo')
                            ->required()
                            ->default(true),
                    ])->columns(2),

                Section::make('Redes Sociais e Contato')
                    ->description('Configurações da instituição para site e e-mails.')
                    ->schema([
                        TextInput::make('celular_whatsapp')
                            ->label('Celular / WhatsApp')
                            ->placeholder('(00) 00000-0000'),
                        TextInput::make('instagram')
                            ->label('Instagram URL')
                            ->placeholder('https://instagram.com/instituicao'),
                        TextInput::make('facebook')
                            ->label('Facebook URL')
                            ->placeholder('https://facebook.com/instituicao'),
                        TextInput::make('youtube')
                            ->label('YouTube URL')
                            ->placeholder('https://youtube.com/c/instituicao'),
                    ])->columns(2),
            ]);
    }
}
