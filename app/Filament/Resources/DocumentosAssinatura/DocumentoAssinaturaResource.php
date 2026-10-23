<?php

declare(strict_types=1);

namespace App\Filament\Resources\DocumentosAssinatura;

use App\Enums\StatusDocumentoAssinatura;
use App\Filament\Resources\DocumentosAssinatura\Pages\CreateDocumentoAssinatura;
use App\Filament\Resources\DocumentosAssinatura\Pages\ListDocumentosAssinatura;
use App\Filament\Resources\DocumentosAssinatura\Pages\ViewDocumentoAssinatura;
use App\Filament\Resources\DocumentosAssinatura\Schemas\DocumentoAssinaturaForm;
use App\Filament\Resources\DocumentosAssinatura\Schemas\DocumentoAssinaturaInfolist;
use App\Filament\Resources\DocumentosAssinatura\Tables\DocumentosAssinaturaTable;
use App\Models\DocumentoAssinatura;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Assinatura eletrônica de documentos avulsos (Onda 69): um modelo de documento preenchido com os dados de um aluno, de um
 * cancelamento ou de um funcionário vira PDF e vai para o Assinafy com os assinantes. Sem edição nem exclusão: o texto
 * enviado e o histórico ficam. Documento de RH só aparece para quem também vê Admissão e Desligamento.
 */
class DocumentoAssinaturaResource extends Resource implements HasShieldPermissions
{
    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'enviar',
            'cancelar',
        ];
    }

    protected static ?string $model = DocumentoAssinatura::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencil;

    protected static UnitEnum|string|null $navigationGroup = 'Secretaria';

    protected static ?int $navigationSort = 64;

    protected static ?string $navigationLabel = 'Documentos para Assinatura';

    protected static ?string $modelLabel = 'Documento para Assinatura';

    protected static ?string $pluralModelLabel = 'Documentos para Assinatura';

    protected static ?string $slug = 'secretaria/documentos-assinatura';

    public static function getEloquentQuery(): Builder
    {
        $consulta = parent::getEloquentQuery()
            ->with(['template', 'criadoPor', 'assinantes'])
            ->withCount([
                'assinantes as assinantes_total',
                'assinantes as assinantes_assinaram' => fn (Builder $assinantes) => $assinantes->where('status', 'assinou'),
            ]);

        // Documento de RH só para quem vê Admissão e Desligamento.
        return auth()->user()?->can('View:ProcessoFuncionario') ? $consulta : $consulta->semRh();
    }

    public static function getNavigationBadge(): ?string
    {
        if (! auth()->user()?->can('ViewAny:DocumentoAssinatura')) {
            return null;
        }

        $consulta = DocumentoAssinatura::query()->where('status', StatusDocumentoAssinatura::Enviado->value);

        $total = (auth()->user()?->can('View:ProcessoFuncionario') ? $consulta : $consulta->semRh())->count();

        return $total > 0 ? (string) $total : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Documentos aguardando assinaturas';
    }

    public static function form(Schema $schema): Schema
    {
        return DocumentoAssinaturaForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DocumentoAssinaturaInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DocumentosAssinaturaTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocumentosAssinatura::route('/'),
            'create' => CreateDocumentoAssinatura::route('/create'),
            'view' => ViewDocumentoAssinatura::route('/{record}'),
        ];
    }
}
