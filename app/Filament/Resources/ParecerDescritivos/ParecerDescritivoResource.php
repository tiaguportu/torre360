<?php

declare(strict_types=1);

namespace App\Filament\Resources\ParecerDescritivos;

use App\Filament\Resources\ParecerDescritivos\Pages\CreateParecerDescritivo;
use App\Filament\Resources\ParecerDescritivos\Pages\EditParecerDescritivo;
use App\Filament\Resources\ParecerDescritivos\Pages\ListParecerDescritivos;
use App\Filament\Resources\ParecerDescritivos\Pages\ViewParecerDescritivo;
use App\Filament\Resources\ParecerDescritivos\Schemas\ParecerDescritivoForm;
use App\Filament\Resources\ParecerDescritivos\Tables\ParecerDescritivosTable;
use App\Models\ParecerDescritivo;
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
 * Pareceres descritivos (Onda 30): rascunho do professor (com apoio opcional de IA), revisão e publicação pela
 * coordenação. Só o publicado aparece no Portal da Família.
 */
class ParecerDescritivoResource extends Resource implements HasShieldPermissions
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
            'publicar',
            'gerar_com_ia',
        ];
    }

    protected static ?string $model = ParecerDescritivo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static UnitEnum|string|null $navigationGroup = 'Acadêmico';

    protected static ?int $navigationSort = 31;

    protected static ?string $modelLabel = 'Parecer Descritivo';

    protected static ?string $pluralModelLabel = 'Pareceres Descritivos';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('matricula', fn (Builder $matriculas) => EscopoProfessor::restringirMatriculas($matriculas, auth()->user()))
            ->with(['matricula.pessoa', 'matricula.turma', 'etapaAvaliativa']);
    }

    public static function form(Schema $schema): Schema
    {
        return ParecerDescritivoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ParecerDescritivosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListParecerDescritivos::route('/'),
            'create' => CreateParecerDescritivo::route('/create'),
            'view' => ViewParecerDescritivo::route('/{record}'),
            'edit' => EditParecerDescritivo::route('/{record}/edit'),
        ];
    }
}
