<?php

namespace App\Filament\Resources\AtendimentoChamados;

use App\Filament\Resources\AtendimentoChamados\Pages\CreateAtendimentoChamado;
use App\Filament\Resources\AtendimentoChamados\Pages\EditAtendimentoChamado;
use App\Filament\Resources\AtendimentoChamados\Pages\ListAtendimentoChamados;
use App\Filament\Resources\AtendimentoChamados\Schemas\AtendimentoChamadoForm;
use App\Filament\Resources\AtendimentoChamados\Tables\AtendimentoChamadosTable;
use App\Models\AtendimentoChamado;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class AtendimentoChamadoResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = AtendimentoChamado::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';

    protected static \UnitEnum|string|null $navigationGroup = 'Comunicação Escolar';

    protected static ?string $modelLabel = 'Chamado de Atendimento';

    protected static ?string $pluralModelLabel = 'Central de Atendimento';

    protected static ?int $navigationSort = 2;

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
        return AtendimentoChamadoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AtendimentoChamadosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAtendimentoChamados::route('/'),
            'create' => CreateAtendimentoChamado::route('/create'),
            'edit' => EditAtendimentoChamado::route('/{record}/edit'),
        ];
    }
}
