<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reunioes;

use App\Filament\Resources\Reunioes\Pages\CreateReuniao;
use App\Filament\Resources\Reunioes\Pages\EditReuniao;
use App\Filament\Resources\Reunioes\Pages\ListReunioes;
use App\Filament\Resources\Reunioes\Pages\ViewReuniao;
use App\Filament\Resources\Reunioes\RelationManagers\AlunosRelationManager;
use App\Filament\Resources\Reunioes\RelationManagers\EncaminhamentosRelationManager;
use App\Filament\Resources\Reunioes\Schemas\ReuniaoForm;
use App\Filament\Resources\Reunioes\Schemas\ReuniaoInfolist;
use App\Filament\Resources\Reunioes\Tables\ReunioesTable;
use App\Models\Reuniao;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Reuniões e atas (Ondas 27 e 32): conselho de classe, reunião pedagógica, devolutiva individual e formação, com pauta, presença, ata
 * que se fecha e encaminhamentos com responsável e prazo. Cada pessoa vê as reuniões que organiza ou em que participa;
 * `VerTodas:Reuniao` (coordenação) abre também as dos outros, menos as sigilosas.
 */
class ReuniaoResource extends Resource implements HasShieldPermissions
{
    public static function getPermissionPrefixes(): array
    {
        return ['view', 'view_any', 'create', 'update', 'delete', 'delete_any', 'ver_todas', 'deliberar'];
    }

    protected static ?string $model = Reuniao::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static UnitEnum|string|null $navigationGroup = 'Acadêmico';

    protected static ?int $navigationSort = 40;

    protected static ?string $modelLabel = 'reunião';

    protected static ?string $pluralModelLabel = 'Reuniões e atas';

    protected static ?string $navigationLabel = 'Reuniões e atas';

    protected static ?string $slug = 'reunioes';

    protected static ?string $recordTitleAttribute = 'titulo';

    /**
     * @return Builder<Reuniao>
     */
    public static function getEloquentQuery(): Builder
    {
        $usuario = auth()->user();

        if (! $usuario) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()->visiveisPara($usuario);
    }

    public static function form(Schema $schema): Schema
    {
        return ReuniaoForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ReuniaoInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReunioesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AlunosRelationManager::class,
            EncaminhamentosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReunioes::route('/'),
            'create' => CreateReuniao::route('/create'),
            'view' => ViewReuniao::route('/{record}'),
            'edit' => EditReuniao::route('/{record}/edit'),
        ];
    }
}
