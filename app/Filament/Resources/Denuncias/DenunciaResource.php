<?php

namespace App\Filament\Resources\Denuncias;

use App\Filament\Resources\Denuncias\Pages\ListDenuncias;
use App\Filament\Resources\Denuncias\Pages\ViewDenuncia;
use App\Filament\Resources\Denuncias\Schemas\DenunciaInfolist;
use App\Filament\Resources\Denuncias\Tables\DenunciasTable;
use App\Models\Denuncia;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ouvidoria e convivência escolar (Ondas 35 e 63): fila restrita ao comitê. Quem não é do comitê da categoria não vê a denúncia
 * na lista nem pelo link direto (a consulta já vem filtrada), e ninguém cria nem edita o relato por aqui.
 */
class DenunciaResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = Denuncia::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static \UnitEnum|string|null $navigationGroup = 'Secretaria';

    protected static ?string $modelLabel = 'Manifestação da ouvidoria';

    protected static ?string $pluralModelLabel = 'Ouvidoria';

    protected static ?string $slug = 'ouvidoria';

    protected static ?int $navigationSort = 90;

    public static function getPermissionPrefixes(): array
    {
        return [
            'view_any',
            'view',
            'tratar',
            'ver_todas',
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('movimentacoes')->visiveisPara(auth()->user());
    }

    public static function infolist(Schema $schema): Schema
    {
        return DenunciaInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DenunciasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDenuncias::route('/'),
            'view' => ViewDenuncia::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
