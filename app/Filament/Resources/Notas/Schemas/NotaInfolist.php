<?php

namespace App\Filament\Resources\Notas\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class NotaInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('avaliacao.label_exibicao')
                    ->label('Avaliação'),
                TextEntry::make('matricula.label_exibicao')
                    ->label('Matrícula'),
                TextEntry::make('valor')
                    ->numeric(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
