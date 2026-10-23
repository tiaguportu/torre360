<?php

namespace App\Filament\Resources\NotificacoesConselhoTutelar;

use App\Filament\Resources\NotificacoesConselhoTutelar\Pages\CreateNotificacaoConselhoTutelar;
use App\Filament\Resources\NotificacoesConselhoTutelar\Pages\EditNotificacaoConselhoTutelar;
use App\Filament\Resources\NotificacoesConselhoTutelar\Pages\ListNotificacoesConselhoTutelar;
use App\Filament\Resources\NotificacoesConselhoTutelar\Schemas\NotificacaoConselhoTutelarForm;
use App\Filament\Resources\NotificacoesConselhoTutelar\Tables\NotificacoesConselhoTutelarTable;
use App\Models\NotificacaoConselhoTutelar;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class NotificacaoConselhoTutelarResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = NotificacaoConselhoTutelar::class;

    protected static ?string $modelLabel = 'Notificação ao Conselho Tutelar';

    protected static ?string $pluralModelLabel = 'Notificações ao Conselho Tutelar';

    protected static string|\UnitEnum|null $navigationGroup = 'Acadêmico';

    protected static ?int $navigationSort = 18;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    public static function form(Schema $schema): Schema
    {
        return NotificacaoConselhoTutelarForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NotificacoesConselhoTutelarTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotificacoesConselhoTutelar::route('/'),
            'create' => CreateNotificacaoConselhoTutelar::route('/create'),
            'edit' => EditNotificacaoConselhoTutelar::route('/{record}/edit'),
        ];
    }
}
