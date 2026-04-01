<?php

namespace App\Filament\Resources\CronogramaAulas;

use App\Filament\Resources\CronogramaAulas\Pages;
use App\Filament\Resources\CronogramaAulas\Schemas\CronogramaAulaForm;
use App\Filament\Resources\CronogramaAulas\Tables\CronogramaAulasTable;
use App\Models\CronogramaAula;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CronogramaAulaResource extends Resource
{
    protected static ?string $model = CronogramaAula::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Secretaria';
    protected static ?int $navigationSort = 4;
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    public static function form(Schema $schema): Schema
    {
        return CronogramaAulaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CronogramaAulasTable::configure($table);
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
            'index' => Pages\ListCronogramaAulas::route('/'),
            'calendar' => Pages\Calendar::route('/calendar'),
            'create' => Pages\CreateCronogramaAula::route('/create'),
            'verifica-conflitos' => Pages\VerificaConflitos::route('/verifica-conflitos'),
            'edit' => Pages\EditCronogramaAula::route('/{record}/edit'),
        ];
    }
}
