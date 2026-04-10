<?php

namespace App\Filament\Resources\Pessoas\Schemas;

use App\Models\Pais;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PessoaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('foto')
                    ->image()
                    ->imageEditor()
                    ->imageCropAspectRatio('3:4')
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
                    ->getOptionLabelFromRecordUsing(fn ($record) => ($record->sigla ? mb_convert_encoding('&#'.(127397 + ord(strtoupper($record->sigla[0]))).';&#'.(127397 + ord(strtoupper($record->sigla[1]))).';', 'UTF-8', 'HTML-ENTITIES').' ' : '').$record->nome
                    )
                    ->live()
                    ->searchable()
                    ->preload(),

                Select::make('naturalidade_id')
                    ->relationship('naturalidade', 'nome', fn ($query) => $query->whereNotNull('nome'))
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->nome}-{$record->estado?->sigla}")
                    ->searchable()
                    ->preload()
                    ->visible(fn ($get) => $get('nacionalidade_id') &&
                        $get('nacionalidade_id') == Pais::where('nome', 'Brasil')->value('id')
                    ),

                TextInput::make('cpf')
                    ->mask('999.999.999-99')
                    ->unique(ignoreRecord: true)
                    ->maxLength(14)
                    ->dehydrateStateUsing(fn (?string $state) => $state ? preg_replace('/[^0-9]/', '', $state) : null),

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

                Select::make('users')
                    ->label('Usuários do Sistema')
                    ->relationship('users', 'name')
                    ->multiple()
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Este campo é apenas informativo. O vínculo de usuários deve ser gerenciado no recurso de Usuários.'),
            ]);
    }
}
