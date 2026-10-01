<?php

namespace App\Filament\Resources\VideoTutorials\Schemas;

use App\Models\VideoTutorial;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class VideoTutorialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('titulo')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('descricao')
                    ->rows(3)
                    ->columnSpanFull(),
                TextInput::make('categoria')
                    ->placeholder('Ex: CRM, Secretaria, Acadêmico, Avaliações...'),
                Select::make('chave_pagina')
                    ->label('Tela relacionada')
                    ->options(VideoTutorial::CHAVES_PAGINA)
                    ->searchable()
                    ->helperText('Se preenchido, este vídeo também aparece no modal de "Ajuda" dessa tela específica.'),
                TextInput::make('ordem')
                    ->label('Ordem de exibição')
                    ->numeric()
                    ->default(0),
                TextInput::make('duracao_segundos')
                    ->label('Duração (segundos)')
                    ->numeric()
                    ->minValue(0),
                Toggle::make('ativo')
                    ->required()
                    ->default(true),
                FileUpload::make('arquivo')
                    ->label('Vídeo (Arquivo)')
                    ->disk('public')
                    ->directory('video-tutoriais')
                    ->visibility('public')
                    ->acceptedFileTypes(['video/mp4', 'video/webm', 'video/ogg'])
                    ->maxSize(40960)
                    ->downloadable()
                    ->openable()
                    ->helperText('Até 40MB. Se preenchido, tem prioridade sobre o link externo.')
                    ->columnSpanFull(),
                TextInput::make('url_externo')
                    ->label('Vídeo (Link Externo)')
                    ->url()
                    ->placeholder('https://www.youtube.com/watch?v=...')
                    ->helperText('Usado apenas quando não houver arquivo enviado (YouTube/Vimeo).')
                    ->columnSpanFull(),
            ]);
    }
}
