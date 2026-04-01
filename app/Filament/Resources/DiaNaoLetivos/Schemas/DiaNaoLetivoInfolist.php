<?php

namespace App\Filament\Resources\DiaNaoLetivos\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class DiaNaoLetivoInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('periodoLetivo.id')
                    ->label('Periodo letivo'),

                TextEntry::make('data')
                    ->date(),
                TextEntry::make('descricao'),
                IconEntry::make('flag_ativo')
                    ->boolean(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
