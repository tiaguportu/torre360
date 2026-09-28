<?php

namespace App\Filament\Resources\AtendimentoSetores;

use App\Filament\Resources\AtendimentoSetores\Pages\CreateAtendimentoSetor;
use App\Filament\Resources\AtendimentoSetores\Pages\EditAtendimentoSetor;
use App\Filament\Resources\AtendimentoSetores\Pages\ListAtendimentoSetores;
use App\Filament\Resources\AtendimentoSetores\Schemas\AtendimentoSetorForm;
use App\Filament\Resources\AtendimentoSetores\Tables\AtendimentoSetoresTable;
use App\Models\AtendimentoSetor;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class AtendimentoSetorResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = AtendimentoSetor::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    protected static \UnitEnum|string|null $navigationGroup = 'Comunicação Escolar';

    protected static ?string $modelLabel = 'Setor de Atendimento';

    protected static ?string $pluralModelLabel = 'Setores de Atendimento';

    protected static ?int $navigationSort = 3;

    public static function getPermissionPrefixes(): array
    {
        return [
            'view_any',
            'view',
            'create',
            'update',
            'delete',
            'delete_any',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return AtendimentoSetorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AtendimentoSetoresTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAtendimentoSetores::route('/'),
            'create' => CreateAtendimentoSetor::route('/create'),
            'edit' => EditAtendimentoSetor::route('/{record}/edit'),
        ];
    }
}
