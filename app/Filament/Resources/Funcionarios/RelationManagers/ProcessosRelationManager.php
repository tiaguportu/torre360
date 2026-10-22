<?php

declare(strict_types=1);

namespace App\Filament\Resources\Funcionarios\RelationManagers;

use App\Enums\TipoProcessoFuncionario;
use App\Filament\Resources\ProcessosFuncionario\Actions\IniciarProcesso;
use App\Filament\Resources\ProcessosFuncionario\ProcessoFuncionarioResource;
use App\Models\ProcessoFuncionario;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Admissões e desligamentos deste funcionário (Onda 65). A lista de providências abre na tela do processo.
 */
class ProcessosRelationManager extends RelationManager
{
    protected static string $relationship = 'processos';

    protected static ?string $title = 'Admissão e Desligamento';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'itens as itens_total',
                'itens as itens_resolvidos' => fn (Builder $itens) => $itens->whereNotNull('concluido_em'),
            ]))
            ->columns([
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge(),
                TextColumn::make('data_prevista')
                    ->label('Data')
                    ->date('d/m/Y'),
                TextColumn::make('status')
                    ->label('Situação')
                    ->badge(),
                TextColumn::make('andamento')
                    ->label('Andamento')
                    ->state(fn (ProcessoFuncionario $record): string => "{$record->itens_resolvidos}/{$record->itens_total}"),
            ])
            ->headerActions([
                IniciarProcesso::make(TipoProcessoFuncionario::Admissao, $this->getOwnerRecord()),
                IniciarProcesso::make(TipoProcessoFuncionario::Desligamento, $this->getOwnerRecord()),
            ])
            ->recordActions([
                Action::make('abrir')
                    ->label('Abrir')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (ProcessoFuncionario $record): string => ProcessoFuncionarioResource::getUrl('view', ['record' => $record])),
            ])
            ->defaultSort('data_prevista', 'desc')
            ->stackedOnMobile();
    }
}
