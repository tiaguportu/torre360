<?php

declare(strict_types=1);

namespace App\Filament\Resources\Objecoes;

use App\Filament\Resources\Objecoes\Pages\CreateObjecao;
use App\Filament\Resources\Objecoes\Pages\EditObjecao;
use App\Filament\Resources\Objecoes\Pages\ListObjecoes;
use App\Filament\Resources\Objecoes\Schemas\ObjecaoForm;
use App\Filament\Resources\Objecoes\Tables\ObjecoesTable;
use App\Models\Objecao;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ObjecaoResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = Objecao::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static UnitEnum|string|null $navigationGroup = 'CRM / Comercial';

    protected static ?string $modelLabel = 'Objeção Comercial';

    protected static ?string $pluralModelLabel = 'Matriz de Objeções';

    protected static ?string $navigationLabel = 'Matriz de Objeções';

    protected static ?int $navigationSort = 75;

    protected static ?string $recordTitleAttribute = 'titulo';

    public static function form(Schema $schema): Schema
    {
        return ObjecaoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ObjecoesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListObjecoes::route('/'),
            'create' => CreateObjecao::route('/create'),
            'edit' => EditObjecao::route('/{record}/edit'),
        ];
    }
}
