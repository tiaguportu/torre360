<?php

namespace App\Filament\Resources\PlanilhaLeiMensalidades;

use App\Filament\Resources\PlanilhaLeiMensalidades\Pages\CreatePlanilhaLeiMensalidade;
use App\Filament\Resources\PlanilhaLeiMensalidades\Pages\EditPlanilhaLeiMensalidade;
use App\Filament\Resources\PlanilhaLeiMensalidades\Pages\ListPlanilhaLeiMensalidades;
use App\Filament\Resources\PlanilhaLeiMensalidades\Schemas\PlanilhaLeiMensalidadeForm;
use App\Filament\Resources\PlanilhaLeiMensalidades\Tables\PlanilhaLeiMensalidadesTable;
use App\Models\PlanilhaLeiMensalidade;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class PlanilhaLeiMensalidadeResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = PlanilhaLeiMensalidade::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Financeiro';

    protected static ?string $navigationLabel = 'Lei da Mensalidade (Lei 9.870/99)';

    protected static ?string $modelLabel = 'Planilha de Custos (Lei 9.870/99)';

    protected static ?string $pluralModelLabel = 'Planilhas de Custos (Lei 9.870/99)';

    protected static ?int $navigationSort = 15;

    public static function getPermissionPrefixes(): array
    {
        return [
            'view_any',
            'view',
            'create',
            'update',
            'delete',
            'delete_any',
            'homologar',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return PlanilhaLeiMensalidadeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlanilhaLeiMensalidadesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlanilhaLeiMensalidades::route('/'),
            'create' => CreatePlanilhaLeiMensalidade::route('/create'),
            'edit' => EditPlanilhaLeiMensalidade::route('/{record}/edit'),
        ];
    }
}
