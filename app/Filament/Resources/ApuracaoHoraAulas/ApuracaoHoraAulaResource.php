<?php

namespace App\Filament\Resources\ApuracaoHoraAulas;

use App\Filament\Resources\ApuracaoHoraAulas\Pages\CreateApuracaoHoraAula;
use App\Filament\Resources\ApuracaoHoraAulas\Pages\EditApuracaoHoraAula;
use App\Filament\Resources\ApuracaoHoraAulas\Pages\ListApuracaoHoraAulas;
use App\Filament\Resources\ApuracaoHoraAulas\Schemas\ApuracaoHoraAulaForm;
use App\Filament\Resources\ApuracaoHoraAulas\Tables\ApuracaoHoraAulasTable;
use App\Models\ApuracaoHoraAula;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ApuracaoHoraAulaResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = ApuracaoHoraAula::class;

    protected static ?string $modelLabel = 'Apuração de Hora-Aula';

    protected static ?string $pluralModelLabel = 'Apurações de Horas-Aulas';

    protected static string|\UnitEnum|null $navigationGroup = 'Gestão de RH';

    protected static ?int $navigationSort = 15;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    public static function form(Schema $schema): Schema
    {
        return ApuracaoHoraAulaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ApuracaoHoraAulasTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApuracaoHoraAulas::route('/'),
            'create' => CreateApuracaoHoraAula::route('/create'),
            'edit' => EditApuracaoHoraAula::route('/{record}/edit'),
        ];
    }
}
