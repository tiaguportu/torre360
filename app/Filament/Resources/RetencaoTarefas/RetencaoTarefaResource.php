<?php

namespace App\Filament\Resources\RetencaoTarefas;

use App\Filament\Resources\RetencaoTarefas\Pages\EditRetencaoTarefa;
use App\Filament\Resources\RetencaoTarefas\Pages\ListRetencaoTarefas;
use App\Filament\Resources\RetencaoTarefas\Schemas\RetencaoTarefaForm;
use App\Filament\Resources\RetencaoTarefas\Tables\RetencaoTarefasTable;
use App\Models\RetencaoTarefa;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RetencaoTarefaResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = RetencaoTarefa::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static string|\UnitEnum|null $navigationGroup = 'Acadêmico';

    protected static ?string $navigationLabel = 'Playbook de Retenção';

    protected static ?string $modelLabel = 'Acompanhamento de Retenção';

    protected static ?string $pluralModelLabel = 'Playbook de Retenção';

    protected static ?int $navigationSort = 5;

    public static function getNavigationBadge(): ?string
    {
        $pendentes = RetencaoTarefa::query()->pendentes()->count();

        return $pendentes > 0 ? (string) $pendentes : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Alunos com acompanhamento de retenção pendente';
    }

    public static function form(Schema $schema): Schema
    {
        return RetencaoTarefaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RetencaoTarefasTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRetencaoTarefas::route('/'),
            'edit' => EditRetencaoTarefa::route('/{record}/edit'),
        ];
    }

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
}
