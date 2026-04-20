<?php

namespace App\Filament\Resources\AvaliacaoHabilidades;

use App\Filament\Resources\AvaliacaoHabilidades\Pages\CreateAvaliacaoHabilidade;
use App\Filament\Resources\AvaliacaoHabilidades\Pages\EditAvaliacaoHabilidade;
use App\Filament\Resources\AvaliacaoHabilidades\Pages\ListAvaliacaoHabilidades;
use App\Filament\Resources\AvaliacaoHabilidades\Schemas\AvaliacaoHabilidadeForm;
use App\Filament\Resources\AvaliacaoHabilidades\Tables\AvaliacaoHabilidadesTable;
use App\Models\AvaliacaoHabilidade;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AvaliacaoHabilidadeResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = AvaliacaoHabilidade::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckCircle;

    protected static string|\UnitEnum|null $navigationGroup = 'Avaliações';

    public static function form(Schema $schema): Schema
    {
        return AvaliacaoHabilidadeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AvaliacaoHabilidadesTable::configure($table);
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
            'index' => ListAvaliacaoHabilidades::route('/'),
            'create' => CreateAvaliacaoHabilidade::route('/create'),
            'edit' => EditAvaliacaoHabilidade::route('/{record}/edit'),
        ];
    }
}
