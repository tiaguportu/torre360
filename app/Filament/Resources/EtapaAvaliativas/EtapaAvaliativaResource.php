<?php

namespace App\Filament\Resources\EtapaAvaliativas;

use App\Filament\Resources\EtapaAvaliativas\Pages\CreateEtapaAvaliativa;
use App\Filament\Resources\EtapaAvaliativas\Pages\EditEtapaAvaliativa;
use App\Filament\Resources\EtapaAvaliativas\Pages\ListEtapaAvaliativas;
use App\Filament\Resources\EtapaAvaliativas\Schemas\EtapaAvaliativaForm;
use App\Filament\Resources\EtapaAvaliativas\Tables\EtapaAvaliativasTable;
use App\Models\EtapaAvaliativa;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EtapaAvaliativaResource extends Resource
{
    protected static ?string $model = EtapaAvaliativa::class;

    protected static ?string $modelLabel = 'Etapa Avaliativa';

    protected static ?string $pluralModelLabel = 'Etapas Avaliativas';

    protected static string|\UnitEnum|null $navigationGroup = 'Avaliações';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    public static function form(Schema $schema): Schema
    {
        return EtapaAvaliativaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EtapaAvaliativasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEtapaAvaliativas::route('/'),
            'create' => CreateEtapaAvaliativa::route('/create'),
            'edit' => EditEtapaAvaliativa::route('/{record}/edit'),
        ];
    }
}
