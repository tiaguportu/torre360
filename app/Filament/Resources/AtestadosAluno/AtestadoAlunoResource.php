<?php

declare(strict_types=1);

namespace App\Filament\Resources\AtestadosAluno;

use App\Filament\Resources\AtestadosAluno\Pages\CreateAtestadoAluno;
use App\Filament\Resources\AtestadosAluno\Pages\ListAtestadosAluno;
use App\Filament\Resources\AtestadosAluno\Schemas\AtestadoAlunoForm;
use App\Filament\Resources\AtestadosAluno\Tables\AtestadosAlunoTable;
use App\Models\AtestadoAluno;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Atestados dos alunos: a secretaria anexa o comprovante e informa o período de afastamento; as faltas do período
 * viram justificadas. Correção de período e cancelamento são ações da lista (não há tela de edição nem exclusão).
 */
class AtestadoAlunoResource extends Resource implements HasShieldPermissions
{
    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'cancelar',
        ];
    }

    protected static ?string $model = AtestadoAluno::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static UnitEnum|string|null $navigationGroup = 'Acadêmico';

    protected static ?int $navigationSort = 41;

    protected static ?string $navigationLabel = 'Atestados e Abono de Faltas';

    protected static ?string $slug = 'atestados-aluno';

    protected static ?string $modelLabel = 'Atestado';

    protected static ?string $pluralModelLabel = 'Atestados';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['matricula.pessoa', 'matricula.turma', 'registradoPor'])
            ->withCount('frequencias');
    }

    public static function form(Schema $schema): Schema
    {
        return AtestadoAlunoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AtestadosAlunoTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAtestadosAluno::route('/'),
            'create' => CreateAtestadoAluno::route('/create'),
        ];
    }
}
