<?php

namespace App\Filament\Resources\Contratos;

use App\Filament\Resources\Contratos\Pages\CreateContrato;
use App\Filament\Resources\Contratos\Pages\EditContrato;
use App\Filament\Resources\Contratos\Pages\ListContratos;
use App\Filament\Resources\Contratos\Schemas\ContratoForm;
use App\Filament\Resources\Contratos\Tables\ContratosTable;
use App\Models\Contrato;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ContratoResource extends Resource
{
    protected static ?string $model = Contrato::class;

    protected static ?string $modelLabel = 'Contrato';

    protected static ?string $pluralModelLabel = 'Contratos';

    protected static string|\UnitEnum|null $navigationGroup = 'Financeiro';

    protected static ?int $navigationSort = 1;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        $emails = collect([$user->email]);
        $pessoa = $user->pessoa;
        if ($pessoa && ! empty($pessoa->email)) {
            $emails->push($pessoa->email);
        }

        $emailsClean = $emails->filter()->map(fn ($e) => strtolower(trim($e)))->unique();

        $count = static::getModel()::query()
            ->whereNotIn('assinafy_status', ['signed', 'completed'])
            ->get()
            ->filter(function (Contrato $contrato) use ($emailsClean) {
                $statusSignatarios = $contrato->getStatusSignatarios();

                return $statusSignatarios->contains(function ($sig) use ($emailsClean) {
                    $sigEmail = strtolower(trim($sig['email'] ?? ''));

                    return $emailsClean->contains($sigEmail) && ($sig['status'] ?? 'pending') === 'pending';
                });
            })
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return ContratoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContratosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\FaturasRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContratos::route('/'),
            'create' => CreateContrato::route('/create'),
            'edit' => EditContrato::route('/{record}/edit'),
        ];
    }
}
