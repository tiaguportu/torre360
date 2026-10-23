<?php

declare(strict_types=1);

namespace App\Filament\Resources\RelatoriosSalvos;

use App\Filament\Resources\RelatoriosSalvos\Pages\CreateRelatorioSalvo;
use App\Filament\Resources\RelatoriosSalvos\Pages\EditRelatorioSalvo;
use App\Filament\Resources\RelatoriosSalvos\Pages\ListRelatoriosSalvos;
use App\Filament\Resources\RelatoriosSalvos\RelationManagers\ExecucoesRelationManager;
use App\Filament\Resources\RelatoriosSalvos\Schemas\RelatorioSalvoForm;
use App\Filament\Resources\RelatoriosSalvos\Tables\RelatoriosSalvosTable;
use App\Models\RelatorioSalvo;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Relatórios configuráveis e agendados (Onda 72): cada pessoa escolhe a fonte dos dados, as colunas, os filtros e o formato,
 * pede o arquivo na hora ou agenda a geração e o aviso a quem precisa.
 */
class RelatorioSalvoResource extends Resource implements HasShieldPermissions
{
    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
        ];
    }

    protected static ?string $model = RelatorioSalvo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static UnitEnum|string|null $navigationGroup = 'Relatórios';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'relatório configurável';

    protected static ?string $pluralModelLabel = 'Relatórios configuráveis';

    protected static ?string $navigationLabel = 'Meus Relatórios';

    protected static ?string $slug = 'relatorios/configuraveis';

    protected static ?string $recordTitleAttribute = 'nome';

    /**
     * Cada pessoa só vê os seus; quem tem `VerTodos:RelatorioSalvo` vê todos.
     *
     * @return Builder<RelatorioSalvo>
     */
    public static function getEloquentQuery(): Builder
    {
        $consulta = parent::getEloquentQuery();
        $usuario = auth()->user();

        return $usuario ? $consulta->visiveisPara($usuario) : $consulta->whereRaw('1 = 0');
    }

    public static function form(Schema $schema): Schema
    {
        return RelatorioSalvoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RelatoriosSalvosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ExecucoesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRelatoriosSalvos::route('/'),
            'create' => CreateRelatorioSalvo::route('/create'),
            'edit' => EditRelatorioSalvo::route('/{record}/edit'),
        ];
    }
}
