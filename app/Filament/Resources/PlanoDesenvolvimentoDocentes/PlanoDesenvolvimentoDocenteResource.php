<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlanoDesenvolvimentoDocentes;

use App\Enums\StatusAcaoDesenvolvimento;
use App\Exceptions\DesenvolvimentoDocenteException;
use App\Filament\Resources\PlanoDesenvolvimentoDocentes\Pages\EditPlanoDesenvolvimentoDocente;
use App\Filament\Resources\PlanoDesenvolvimentoDocentes\Pages\ListPlanoDesenvolvimentoDocentes;
use App\Filament\Resources\PlanoDesenvolvimentoDocentes\Pages\ViewPlanoDesenvolvimentoDocente;
use App\Filament\Resources\PlanoDesenvolvimentoDocentes\RelationManagers\AcoesRelationManager;
use App\Filament\Resources\PlanoDesenvolvimentoDocentes\Schemas\PlanoDesenvolvimentoDocenteForm;
use App\Filament\Resources\PlanoDesenvolvimentoDocentes\Tables\PlanoDesenvolvimentoDocentesTable;
use App\Models\AvaliacaoDocente;
use App\Models\PlanoDesenvolvimentoDocente;
use App\Services\DesenvolvimentoDocenteService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Plano de desenvolvimento individual (melhoria da Onda 19): nasce de uma avaliação finalizada, a coordenação o ajusta e
 * ativa, e o professor dá ciência e registra o andamento. Dado sensível, recortado por `visiveisPara()`.
 */
class PlanoDesenvolvimentoDocenteResource extends Resource implements HasShieldPermissions
{
    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'ativar',
            'concluir',
            'dar_ciencia',
            'atualizar_acao',
        ];
    }

    protected static ?string $model = PlanoDesenvolvimentoDocente::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static UnitEnum|string|null $navigationGroup = 'RH';

    protected static ?int $navigationSort = 32;

    protected static ?string $navigationLabel = 'Planos de Desenvolvimento';

    protected static ?string $modelLabel = 'Plano de Desenvolvimento Docente';

    protected static ?string $pluralModelLabel = 'Planos de Desenvolvimento Docente';

    public static function getEloquentQuery(): Builder
    {
        $emAberto = [StatusAcaoDesenvolvimento::Pendente->value, StatusAcaoDesenvolvimento::EmAndamento->value];

        return parent::getEloquentQuery()
            ->visiveisPara(auth()->user())
            ->with(['funcionario.pessoa', 'avaliacao.ciclo.instrumento'])
            ->withCount([
                'acoes',
                'acoes as acoes_atrasadas_count' => fn (Builder $q) => $q->whereIn('status', $emAberto)->whereDate('prazo', '<', today()->toDateString()),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return PlanoDesenvolvimentoDocenteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlanoDesenvolvimentoDocentesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AcoesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlanoDesenvolvimentoDocentes::route('/'),
            'view' => ViewPlanoDesenvolvimentoDocente::route('/{record}'),
            'edit' => EditPlanoDesenvolvimentoDocente::route('/{record}/edit'),
        ];
    }

    /**
     * Ação usada na lista e na tela de uma avaliação finalizada: cria o plano de desenvolvimento (com ações sugeridas)
     * ou abre o que já existe.
     */
    public static function acaoPlanoDaAvaliacao(): Action
    {
        return Action::make('planoDesenvolvimento')
            ->label(fn (AvaliacaoDocente $record): string => self::planoDaAvaliacao($record) ? 'Abrir plano de desenvolvimento' : 'Criar plano de desenvolvimento')
            ->icon('heroicon-o-flag')
            ->color('info')
            ->visible(function (AvaliacaoDocente $record): bool {
                if (! $record->estaFinalizada()) {
                    return false;
                }

                $plano = self::planoDaAvaliacao($record);

                return $plano
                    ? (bool) auth()->user()?->can('view', $plano)
                    : (bool) auth()->user()?->can('create', PlanoDesenvolvimentoDocente::class);
            })
            ->requiresConfirmation(fn (AvaliacaoDocente $record): bool => self::planoDaAvaliacao($record) === null)
            ->modalHeading('Criar plano de desenvolvimento')
            ->modalDescription('O plano nasce em rascunho, com uma ação sugerida para cada critério abaixo de 70% da escala. O professor só o vê depois que você o ativar.')
            ->action(function (AvaliacaoDocente $record) {
                $plano = self::planoDaAvaliacao($record);

                if (! $plano) {
                    try {
                        $plano = app(DesenvolvimentoDocenteService::class)->criarPlano($record, auth()->user());
                    } catch (DesenvolvimentoDocenteException $e) {
                        Notification::make()->title('Não foi possível criar o plano')->body($e->getMessage())->danger()->send();

                        return null;
                    }

                    Notification::make()->title('Plano criado em rascunho')->success()->send();
                }

                return redirect(auth()->user()->can('update', $plano)
                    ? self::getUrl('edit', ['record' => $plano])
                    : self::getUrl('view', ['record' => $plano]));
            });
    }

    private static function planoDaAvaliacao(AvaliacaoDocente $avaliacao): ?PlanoDesenvolvimentoDocente
    {
        return PlanoDesenvolvimentoDocente::query()->where('avaliacao_docente_id', $avaliacao->id)->first();
    }
}
