<?php

namespace App\Filament\Resources\TipoDocumentos\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TipoDocumentoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nome')
                    ->required()
                    ->columnSpanFull(),
                Toggle::make('flag_obrigatorio')
                    ->label('Obrigatório')
                    ->required()
                    ->default(true),
                Select::make('cursos')
                    ->relationship('cursos', 'nome_externo', modifyQueryUsing: fn ($query) => $query->whereNotNull('nome_externo'))
                    ->multiple()
                    ->searchable()
                    ->preload(),
                Select::make('turmas')
                    ->relationship('turmas', 'nome', modifyQueryUsing: fn ($query) => $query->whereNotNull('nome'))
                    ->multiple()
                    ->searchable()
                    ->preload(),
                Select::make('matriculas')
                    ->relationship('matriculas', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record) => ($record->pessoa?->nome ?? 'Aluno Desconhecido')." (ID: {$record->id})")
                    ->multiple()
                    ->searchable()
                    ->preload(),
                FileUpload::make('modelo_arquivo')
                    ->label('Modelo (Arquivo)')
                    ->directory('tipos-documentos-modelos')
                    ->visibility('public')
                    ->downloadable()
                    ->openable()
                    ->columnSpanFull(),
                TextInput::make('modelo_link')
                    ->label('Modelo (Link)')
                    ->url()
                    ->placeholder('https://...')
                    ->columnSpanFull(),
            ]);
    }
}
