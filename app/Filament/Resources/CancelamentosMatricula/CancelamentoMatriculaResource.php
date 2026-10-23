<?php

declare(strict_types=1);

namespace App\Filament\Resources\CancelamentosMatricula;

use App\Filament\Resources\CancelamentosMatricula\Pages\CreateCancelamentoMatricula;
use App\Filament\Resources\CancelamentosMatricula\Pages\ListCancelamentosMatricula;
use App\Filament\Resources\CancelamentosMatricula\Schemas\CancelamentoMatriculaForm;
use App\Filament\Resources\CancelamentosMatricula\Tables\CancelamentosMatriculaTable;
use App\Models\CancelamentoMatricula;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Cancelamento de matrícula e distrato (Onda 61): a família pede pelo Portal (ou a secretaria registra aqui), a secretaria
 * decide o destino de cada fatura, imprime o distrato e conclui. Não há edição nem exclusão: o pedido tem histórico.
 */
class CancelamentoMatriculaResource extends Resource implements HasShieldPermissions
{
    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'aprovar',
            'concluir',
        ];
    }

    protected static ?string $model = CancelamentoMatricula::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentMinus;

    protected static UnitEnum|string|null $navigationGroup = 'Secretaria';

    protected static ?int $navigationSort = 63;

    protected static ?string $navigationLabel = 'Cancelamento de Matrícula';

    protected static ?string $modelLabel = 'Cancelamento de Matrícula';

    protected static ?string $pluralModelLabel = 'Cancelamentos de Matrícula';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['matricula.pessoa', 'matricula.turma.serie', 'matricula.contrato', 'documentosAssinatura']);
    }

    public static function form(Schema $schema): Schema
    {
        return CancelamentoMatriculaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CancelamentosMatriculaTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCancelamentosMatricula::route('/'),
            'create' => CreateCancelamentoMatricula::route('/create'),
        ];
    }
}
