<?php

namespace App\Filament\Resources\LandingLeads;

use App\Filament\Resources\LandingLeads\Pages\ListLandingLeads;
use App\Filament\Resources\LandingLeads\Tables\LandingLeadsTable;
use App\Models\LandingLead;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Pedidos de demonstração recebidos pela landing page do produto (`/`).
 */
class LandingLeadResource extends Resource implements HasShieldPermissions
{
    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'update',
            'delete',
            'delete_any',
        ];
    }

    protected static ?string $model = LandingLead::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static UnitEnum|string|null $navigationGroup = 'CRM / Comercial';

    protected static ?string $modelLabel = 'Lead da Landing Page';

    protected static ?string $pluralModelLabel = 'Leads da Landing Page';

    protected static ?string $navigationLabel = 'Leads da Landing Page';

    protected static ?string $recordTitleAttribute = 'nome';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $novos = LandingLead::novos()->count();

        return $novos > 0 ? (string) $novos : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return LandingLeadsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLandingLeads::route('/'),
        ];
    }
}
