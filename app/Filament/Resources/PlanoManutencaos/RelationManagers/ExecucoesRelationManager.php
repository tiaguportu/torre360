<?php

declare(strict_types=1);

namespace App\Filament\Resources\PlanoManutencaos\RelationManagers;

use App\Enums\StatusExecucaoManutencao;
use App\Filament\Resources\OrdemServicoResource;
use App\Models\ExecucaoManutencao;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Histórico das manutenções do plano (somente leitura): vencimento previsto, ordem de serviço, resultado.
 */
class ExecucoesRelationManager extends RelationManager
{
    protected static string $relationship = 'execucoes';

    protected static ?string $title = 'Histórico de execuções';

    protected static ?string $modelLabel = 'execução';

    protected static ?string $pluralModelLabel = 'execuções';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['ordemServico', 'registradoPor']))
            ->defaultSort('data_prevista', 'desc')
            ->columns([
                TextColumn::make('data_prevista')->label('Vencimento')->date('d/m/Y')->sortable(),
                TextColumn::make('status')->label('Situação')->badge(),
                TextColumn::make('concluida_em')->label('Executada em')->date('d/m/Y')->placeholder('—'),
                TextColumn::make('ordem_servico_id')
                    ->label('Ordem de serviço')
                    ->formatStateUsing(fn ($state): string => $state ? "OS #{$state}" : '—'),
                TextColumn::make('checklist')
                    ->label('Checklist')
                    ->state(function (ExecucaoManutencao $record): string {
                        $itens = (array) $record->checklist_resultado;

                        if ($itens === []) {
                            return '—';
                        }

                        $naoConformes = count(array_filter($itens, fn ($i): bool => ! ($i['ok'] ?? true)));

                        return $naoConformes === 0 ? 'Tudo conforme' : "{$naoConformes} item(ns) não conforme(s)";
                    })
                    ->color(fn (string $state): ?string => str_contains($state, 'não conforme') ? 'danger' : null),
                TextColumn::make('registradoPor.name')->label('Registrado por')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Situação')->options(StatusExecucaoManutencao::class),
            ])
            ->recordActions([
                Action::make('abrirOrdem')
                    ->label('Abrir OS')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (ExecucaoManutencao $record): ?string => $record->ordem_servico_id
                        ? OrdemServicoResource::getUrl('edit', ['record' => $record->ordem_servico_id])
                        : null)
                    ->visible(fn (ExecucaoManutencao $record): bool => $record->ordem_servico_id !== null && (bool) auth()->user()?->can('ViewAny:OrdemServico')),
            ])
            ->stackedOnMobile();
    }
}
