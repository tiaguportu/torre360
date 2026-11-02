<?php

namespace App\Filament\Resources\DenunciaComites;

use App\Enums\CategoriaDenuncia;
use App\Filament\Resources\DenunciaComites\Pages\ManageDenunciaComites;
use App\Models\DenunciaComite;
use App\Models\User;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Comitê da ouvidoria (Onda 35): quem lê as manifestações de cada categoria. Categoria em branco: a pessoa acompanha todas.
 */
class DenunciaComiteResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = DenunciaComite::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static \UnitEnum|string|null $navigationGroup = 'Secretaria';

    protected static ?string $modelLabel = 'Membro do comitê da ouvidoria';

    protected static ?string $pluralModelLabel = 'Comitê da Ouvidoria';

    protected static ?string $slug = 'comite-ouvidoria';

    protected static ?int $navigationSort = 91;

    public static function getPermissionPrefixes(): array
    {
        return ['gerenciar'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')
                ->label('Pessoa')
                ->options(fn (): array => User::query()->ativos()->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->required(),
            Select::make('categoria')
                ->label('Categoria que acompanha')
                ->options(CategoriaDenuncia::class)
                ->placeholder('Todas as categorias'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('Pessoa')->searchable(),
                TextColumn::make('categoria')->label('Categoria')->badge()->placeholder('Todas'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageDenunciaComites::route('/')];
    }
}
