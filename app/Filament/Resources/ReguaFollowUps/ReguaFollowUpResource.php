<?php

namespace App\Filament\Resources\ReguaFollowUps;

use App\Filament\Resources\ReguaFollowUps\Pages\CreateReguaFollowUp;
use App\Filament\Resources\ReguaFollowUps\Pages\EditReguaFollowUp;
use App\Filament\Resources\ReguaFollowUps\Pages\ListReguaFollowUps;
use App\Filament\Resources\ReguaFollowUps\Schemas\ReguaFollowUpForm;
use App\Filament\Resources\ReguaFollowUps\Tables\ReguaFollowUpsTable;
use App\Models\ReguaFollowUp;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ReguaFollowUpResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = ReguaFollowUp::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static UnitEnum|string|null $navigationGroup = 'CRM / Comercial';

    protected static ?string $navigationLabel = 'Régua de Follow-up';

    protected static ?string $modelLabel = 'Régua de Follow-up';

    protected static ?string $pluralModelLabel = 'Réguas de Follow-up';

    protected static ?int $navigationSort = 6;

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'delete_any',
            'execute',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return ReguaFollowUpForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReguaFollowUpsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReguaFollowUps::route('/'),
            'create' => CreateReguaFollowUp::route('/create'),
            'edit' => EditReguaFollowUp::route('/{record}/edit'),
        ];
    }
}
