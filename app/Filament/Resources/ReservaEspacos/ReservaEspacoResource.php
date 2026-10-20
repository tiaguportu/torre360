<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReservaEspacos;

use App\Filament\Resources\ReservaEspacos\Pages\CreateReservaEspaco;
use App\Filament\Resources\ReservaEspacos\Pages\EditReservaEspaco;
use App\Filament\Resources\ReservaEspacos\Pages\ListReservaEspacos;
use App\Filament\Resources\ReservaEspacos\Pages\OcupacaoEspacos;
use App\Filament\Resources\ReservaEspacos\Schemas\ReservaEspacoForm;
use App\Filament\Resources\ReservaEspacos\Tables\ReservaEspacosTable;
use App\Models\ReservaEspaco;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Agendamento pontual de espaços (auditório, quadra, laboratório) fora da grade horária semanal fixa. Qualquer
 * papel com acesso vê o que está ocupado; cada pessoa gerencia as próprias reservas, e a gestão aprova e cancela.
 */
class ReservaEspacoResource extends Resource implements HasShieldPermissions
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
            'confirmar',
        ];
    }

    protected static ?string $model = ReservaEspaco::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static UnitEnum|string|null $navigationGroup = 'Calendário e Horários';

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'Reserva de Espaço';

    protected static ?string $pluralModelLabel = 'Reservas de Espaços';

    protected static ?string $recordTitleAttribute = 'motivo';

    public static function form(Schema $schema): Schema
    {
        return ReservaEspacoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReservaEspacosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReservaEspacos::route('/'),
            'create' => CreateReservaEspaco::route('/create'),
            'ocupacao' => OcupacaoEspacos::route('/ocupacao'),
            'edit' => EditReservaEspaco::route('/{record}/edit'),
        ];
    }
}
