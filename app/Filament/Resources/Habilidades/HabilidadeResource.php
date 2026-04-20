<?php

namespace App\Filament\Resources\Habilidades;

use App\Filament\Resources\Habilidades\Pages\CreateHabilidade;
use App\Filament\Resources\Habilidades\Pages\EditHabilidade;
use App\Filament\Resources\Habilidades\Pages\ListHabilidades;
use App\Filament\Resources\Habilidades\Schemas\HabilidadeForm;
use App\Filament\Resources\Habilidades\Tables\HabilidadesTable;
use App\Models\Habilidade;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class HabilidadeResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = Habilidade::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Currículo (BNCC)';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return HabilidadeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HabilidadesTable::configure($table);
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
            'index' => ListHabilidades::route('/'),
            'create' => CreateHabilidade::route('/create'),
            'edit' => EditHabilidade::route('/{record}/edit'),
        ];
    }
}
