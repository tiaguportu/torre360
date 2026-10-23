<?php

declare(strict_types=1);

namespace App\Filament\Resources\Candidatos;

use App\Filament\Resources\Candidatos\Pages\CreateCandidato;
use App\Filament\Resources\Candidatos\Pages\EditCandidato;
use App\Filament\Resources\Candidatos\Pages\ListCandidatos;
use App\Filament\Resources\Candidatos\Pages\ViewCandidato;
use App\Filament\Resources\Candidatos\Schemas\CandidatoForm;
use App\Filament\Resources\Candidatos\Schemas\CandidatoInfolist;
use App\Filament\Resources\Candidatos\Tables\CandidatosTable;
use App\Models\Candidato;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CandidatoResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = Candidato::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static UnitEnum|string|null $navigationGroup = 'RH';

    protected static ?string $modelLabel = 'Candidato';

    protected static ?string $pluralModelLabel = 'Banco de Talentos / Currículos';

    protected static ?string $recordTitleAttribute = 'nome_completo';

    public static function form(Schema $schema): Schema
    {
        return CandidatoForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CandidatoInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CandidatosTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCandidatos::route('/'),
            'create' => CreateCandidato::route('/create'),
            'view' => ViewCandidato::route('/{record}'),
            'edit' => EditCandidato::route('/{record}/edit'),
        ];
    }
}
