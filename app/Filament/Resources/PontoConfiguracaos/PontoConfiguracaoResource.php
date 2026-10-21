<?php

declare(strict_types=1);

namespace App\Filament\Resources\PontoConfiguracaos;

use App\Filament\Resources\PontoConfiguracaos\Pages\CreatePontoConfiguracao;
use App\Filament\Resources\PontoConfiguracaos\Pages\EditPontoConfiguracao;
use App\Filament\Resources\PontoConfiguracaos\Pages\ListPontoConfiguracaos;
use App\Filament\Resources\PontoConfiguracaos\Schemas\PontoConfiguracaoForm;
use App\Filament\Resources\PontoConfiguracaos\Tables\PontoConfiguracaosTable;
use App\Models\PontoConfiguracao;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Configuração do ponto por unidade: modo de operação, requisitos legais do REP-A/REP-P (com o que falta e como
 * resolver) e tolerâncias. Só a administração acessa.
 */
class PontoConfiguracaoResource extends Resource implements HasShieldPermissions
{
    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
        ];
    }

    protected static ?string $model = PontoConfiguracao::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static UnitEnum|string|null $navigationGroup = 'RH';

    protected static ?int $navigationSort = 24;

    protected static ?string $modelLabel = 'Configuração do Ponto';

    protected static ?string $pluralModelLabel = 'Configuração do Ponto';

    protected static ?string $navigationLabel = 'Configuração do Ponto';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('unidade');
    }

    public static function form(Schema $schema): Schema
    {
        return PontoConfiguracaoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PontoConfiguracaosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPontoConfiguracaos::route('/'),
            'create' => CreatePontoConfiguracao::route('/create'),
            'edit' => EditPontoConfiguracao::route('/{record}/edit'),
        ];
    }
}
