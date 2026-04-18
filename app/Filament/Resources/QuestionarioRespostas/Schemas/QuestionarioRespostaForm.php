<?php

namespace App\Filament\Resources\QuestionarioRespostas\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class QuestionarioRespostaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('questionario_id')
                    ->relationship('questionario', 'id')
                    ->required(),
                Select::make('user_id')
                    ->relationship('user', 'name'),
                TextInput::make('perfil_institucional'),
                DateTimePicker::make('inicio_preenchimento'),
                DateTimePicker::make('fim_preenchimento'),
                TextInput::make('status')
                    ->required()
                    ->default('pendente'),
            ]);
    }
}
