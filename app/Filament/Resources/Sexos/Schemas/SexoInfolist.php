<?php

namespace App\Filament\Resources\Sexos\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SexoInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('nome'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
