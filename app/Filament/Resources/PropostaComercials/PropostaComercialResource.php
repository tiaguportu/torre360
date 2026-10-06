<?php

namespace App\Filament\Resources\PropostaComercials;

use App\Filament\Resources\PropostaComercials\Pages\CreatePropostaComercial;
use App\Filament\Resources\PropostaComercials\Pages\EditPropostaComercial;
use App\Filament\Resources\PropostaComercials\Pages\ListPropostaComercials;
use App\Filament\Resources\PropostaComercials\Schemas\PropostaComercialForm;
use App\Filament\Resources\PropostaComercials\Tables\PropostaComercialsTable;
use App\Models\PropostaComercial;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class PropostaComercialResource extends Resource implements HasShieldPermissions
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
            'aprovar',
        ];
    }

    protected static ?string $model = PropostaComercial::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static UnitEnum|string|null $navigationGroup = 'CRM / Comercial';

    protected static ?string $navigationLabel = 'Propostas Comerciais';

    protected static ?string $modelLabel = 'Proposta Comercial';

    protected static ?string $pluralModelLabel = 'Propostas Comerciais';

    protected static ?string $recordTitleAttribute = 'codigo';

    protected static ?int $navigationSort = 35;

    public static function form(Schema $schema): Schema
    {
        return PropostaComercialForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PropostaComercialsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPropostaComercials::route('/'),
            'create' => CreatePropostaComercial::route('/create'),
            'edit' => EditPropostaComercial::route('/{record}/edit'),
        ];
    }
}
