<?php

namespace App\Filament\Resources\CampanhaMarketings;

use App\Filament\Resources\CampanhaMarketings\Pages\CreateCampanhaMarketing;
use App\Filament\Resources\CampanhaMarketings\Pages\EditCampanhaMarketing;
use App\Filament\Resources\CampanhaMarketings\Pages\ListCampanhaMarketings;
use App\Filament\Resources\CampanhaMarketings\Schemas\CampanhaMarketingForm;
use App\Filament\Resources\CampanhaMarketings\Tables\CampanhaMarketingsTable;
use App\Models\CampanhaMarketing;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CampanhaMarketingResource extends Resource implements HasShieldPermissions
{
    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'delete_any',
        ];
    }

    protected static ?string $model = CampanhaMarketing::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static UnitEnum|string|null $navigationGroup = 'CRM / Comercial';

    protected static ?string $modelLabel = 'Campanha de Marketing';

    protected static ?string $pluralModelLabel = 'Campanhas de Marketing';

    protected static ?string $navigationLabel = 'Campanhas de Marketing';

    protected static ?string $recordTitleAttribute = 'nome';

    public static function form(Schema $schema): Schema
    {
        return CampanhaMarketingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CampanhaMarketingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCampanhaMarketings::route('/'),
            'create' => CreateCampanhaMarketing::route('/create'),
            'edit' => EditCampanhaMarketing::route('/{record}/edit'),
        ];
    }
}
