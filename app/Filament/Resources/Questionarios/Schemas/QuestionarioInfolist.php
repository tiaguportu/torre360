<?php

namespace App\Filament\Resources\Questionarios\Schemas;

use App\Models\Questionario;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class QuestionarioInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('titulo'),
                TextEntry::make('descricao')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('inicio_aplicacao')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('fim_aplicacao')
                    ->dateTime()
                    ->placeholder('-'),
                IconEntry::make('is_anonimo')
                    ->boolean(),
                IconEntry::make('is_ativo')
                    ->boolean(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Questionario $record): bool => $record->trashed()),
            ]);
    }
}
