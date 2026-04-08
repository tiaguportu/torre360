<?php

namespace App\Filament\Resources\PlanoContas;

use App\Filament\Resources\PlanoContas\Pages\CreatePlanoConta;
use App\Filament\Resources\PlanoContas\Pages\EditPlanoConta;
use App\Filament\Resources\PlanoContas\Pages\ListPlanoContas;
use App\Filament\Resources\PlanoContas\Schemas\PlanoContaForm;
use App\Filament\Resources\PlanoContas\Tables\PlanoContasTable;
use App\Models\PlanoConta;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PlanoContaResource extends Resource
{
    protected static ?string $model = PlanoConta::class;

    protected static ?string $modelLabel = 'Plano de Contas';

    protected static ?string $pluralModelLabel = 'Plano de Contas';

    protected static string|\UnitEnum|null $navigationGroup = 'Financeiro';

    protected static ?int $navigationSort = 6;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    public static function form(Schema $schema): Schema
    {
        return PlanoContaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlanoContasTable::configure($table);
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
            'index' => ListPlanoContas::route('/'),
            'create' => CreatePlanoConta::route('/create'),
            'edit' => EditPlanoConta::route('/{record}/edit'),
        ];
    }
}
