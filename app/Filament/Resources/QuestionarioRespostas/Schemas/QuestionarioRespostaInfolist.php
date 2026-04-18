<?php

namespace App\Filament\Resources\QuestionarioRespostas\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class QuestionarioRespostaInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('questionario.id')
                    ->label('Questionario'),
                TextEntry::make('user.name')
                    ->label('User')
                    ->placeholder('-'),
                TextEntry::make('perfil_institucional')
                    ->placeholder('-'),
                TextEntry::make('inicio_preenchimento')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('fim_preenchimento')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('status'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
