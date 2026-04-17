<?php

namespace App\Filament\Resources\DocumentoInseridos\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class DocumentoInseridoInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('documentoObrigatorio.id')
                    ->label('Documento obrigatorio'),
                TextEntry::make('matricula.id')
                    ->label('Matricula'),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('observacoes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('arquivo_path'),
                TextEntry::make('nome_arquivo_original')
                    ->placeholder('-'),
                TextEntry::make('hash_arquivo')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
