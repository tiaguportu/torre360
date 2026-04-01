<?php

namespace App\Filament\Resources\Alunos\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use App\Models\Pais;
use Filament\Schemas\Schema;

class AlunoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('foto')
                    ->image()
                    ->imageEditor()
                    ->imageEditorAspectRatios(['3:4'])
                    ->directory('pessoas_fotos'),
                    
                TextInput::make('nome')
                    ->required()
                    ->maxLength(255),
                    
                DatePicker::make('data_nascimento')
                    ->label('Data de Nascimento'),
                    
                Select::make('nacionalidade_id')
                    ->relationship('nacionalidade', 'nome', fn ($query) => $query->whereNotNull('nome'))
                    ->default(fn () => Pais::where('nome', 'Brasil')->value('id'))
                    ->getOptionLabelFromRecordUsing(fn ($record) => 
                        ($record->sigla ? mb_convert_encoding('&#' . (127397 + ord(strtoupper($record->sigla[0]))) . ';&#' . (127397 + ord(strtoupper($record->sigla[1]))) . ';', 'UTF-8', 'HTML-ENTITIES') . ' ' : '') . $record->nome
                    )
                    ->live()
                    ->searchable()
                    ->preload(),
                    
                Select::make('naturalidade_id')
                    ->relationship('naturalidade', 'nome', fn ($query) => $query->whereNotNull('nome'))
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->nome}-{$record->estado?->sigla}")
                    ->searchable()
                    ->preload()
                    ->visible(fn ($get) => 
                        $get('nacionalidade_id') && 
                        $get('nacionalidade_id') == Pais::where('nome', 'Brasil')->value('id')
                    ),
                    
                TextInput::make('cpf')
                    ->unique('pessoa', ignoreRecord: true)
                    ->mask('999.999.999-99')
                    ->maxLength(14),
                    
                TextInput::make('email')
                    ->email()
                    ->maxLength(255),
                    
                TextInput::make('telefone')
                    ->tel()
                    ->maxLength(20),
                    
                Select::make('sexo_id')
                    ->relationship('sexo', 'nome', fn ($query) => $query->whereNotNull('nome'))
                    ->searchable()
                    ->preload(),
                    
                Select::make('cor_raca_id')
                    ->relationship('corRaca', 'nome', fn ($query) => $query->whereNotNull('nome'))
                    ->searchable()
                    ->preload(),

                Select::make('endereco_id')
                    ->relationship('endereco', 'logradouro', fn ($query) => $query->whereNotNull('logradouro'))
                    ->searchable()
                    ->preload(),
            ]);
    }
}
