<?php

namespace App\Filament\Resources\EtapaAvaliatiwas;

use App\Filament\Resources\EtapaAvaliatiwas\Pages\CreateEtapaAvaliativa;
use App\Filament\Resources\EtapaAvaliatiwas\Pages\EditEtapaAvaliativa;
use App\Filament\Resources\EtapaAvaliatiwas\Pages\ListEtapaAvaliatiwas;
use App\Filament\Resources\EtapaAvaliatiwas\Schemas\EtapaAvaliativaForm;
use App\Filament\Resources\EtapaAvaliatiwas\Tables\EtapaAvaliatiwasTable;
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

    protected static string|\UnitEnum|null $navigationGroup = 'Acadêmico';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    public static function form(Schema $schema): Schema
    {
        return EtapaAvaliativaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EtapaAvaliatiwasTable::configure($table);
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
            'index' => ListEtapaAvaliatiwas::route('/'),
            'create' => CreateEtapaAvaliativa::route('/create'),
            'edit' => EditEtapaAvaliativa::route('/{record}/edit'),
        ];
    }
}
