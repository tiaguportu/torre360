<?php

declare(strict_types=1);

namespace App\Filament\Resources\PortfolioItems;

use App\Filament\Resources\PortfolioItems\Pages\CreatePortfolioItem;
use App\Filament\Resources\PortfolioItems\Pages\EditPortfolioItem;
use App\Filament\Resources\PortfolioItems\Pages\ListPortfolioItems;
use App\Filament\Resources\PortfolioItems\Schemas\PortfolioItemForm;
use App\Filament\Resources\PortfolioItems\Tables\PortfolioItemsTable;
use App\Models\PortfolioItem;
use App\Support\EscopoProfessor;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Portfólio do aluno (Onda 30): evidências de aprendizagem ligadas às habilidades. Quem atua como professor só vê os
 * alunos das próprias turmas.
 */
class PortfolioItemResource extends Resource implements HasShieldPermissions
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

    protected static ?string $model = PortfolioItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static UnitEnum|string|null $navigationGroup = 'Acadêmico';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'Item do Portfólio';

    protected static ?string $pluralModelLabel = 'Portfólio do Aluno';

    protected static ?string $navigationLabel = 'Portfólio do Aluno';

    protected static ?string $recordTitleAttribute = 'titulo';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('matricula', fn (Builder $matriculas) => EscopoProfessor::restringirMatriculas($matriculas, auth()->user()))
            ->with(['matricula.pessoa', 'matricula.turma', 'habilidades']);
    }

    public static function form(Schema $schema): Schema
    {
        return PortfolioItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PortfolioItemsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPortfolioItems::route('/'),
            'create' => CreatePortfolioItem::route('/create'),
            'edit' => EditPortfolioItem::route('/{record}/edit'),
        ];
    }
}
