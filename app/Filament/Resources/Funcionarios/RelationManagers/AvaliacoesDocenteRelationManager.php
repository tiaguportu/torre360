<?php

declare(strict_types=1);

namespace App\Filament\Resources\Funcionarios\RelationManagers;

use App\Enums\StatusAvaliacaoDocente;
use App\Filament\Resources\AvaliacaoDocentes\AvaliacaoDocenteResource;
use App\Models\AvaliacaoDocente;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Histórico de avaliações de desempenho do funcionário, na ficha dele. Só aparece para quem pode consultar
 * avaliações; quem atua como professor não vê esta aba (o professor consulta as próprias no menu de Avaliações).
 */
class AvaliacoesDocenteRelationManager extends RelationManager
{
    protected static string $relationship = 'avaliacoesDocente';

    protected static ?string $title = 'Avaliações de Desempenho';

    protected static ?string $modelLabel = 'avaliação';

    protected static ?string $pluralModelLabel = 'avaliações';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user !== null
            && $user->can('ViewAny:AvaliacaoDocente')
            && ! $user->atuaComoProfessor();
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->visiveisPara(auth()->user())->with(['ciclo', 'avaliador']))
            ->defaultSort('data_observacao', 'desc')
            ->columns([
                TextColumn::make('ciclo.nome')->label('Ciclo')->sortable(),
                TextColumn::make('data_observacao')->label('Observação')->date('d/m/Y')->sortable(),
                TextColumn::make('avaliador.name')->label('Avaliador')->placeholder('—'),
                TextColumn::make('pontuacao_percentual')
                    ->label('Pontuação')
                    ->suffix('%')
                    ->numeric(1)
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('status')->label('Situação')->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Situação')->options(StatusAvaliacaoDocente::class),
            ])
            ->recordActions([
                Action::make('abrir')
                    ->label('Abrir')
                    ->icon('heroicon-o-eye')
                    ->url(fn (AvaliacaoDocente $record): string => AvaliacaoDocenteResource::getUrl('view', ['record' => $record]))
                    ->visible(fn (AvaliacaoDocente $record): bool => (bool) auth()->user()?->can('view', $record)),
            ])
            ->stackedOnMobile();
    }
}
