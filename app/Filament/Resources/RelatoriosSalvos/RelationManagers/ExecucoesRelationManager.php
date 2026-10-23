<?php

declare(strict_types=1);

namespace App\Filament\Resources\RelatoriosSalvos\RelationManagers;

use App\Models\ExecucaoRelatorio;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Histórico do relatório (Onda 72): cada rodada, de quem foi e o arquivo, enquanto a retenção não o apaga. Só leitura.
 */
class ExecucoesRelationManager extends RelationManager
{
    protected static string $relationship = 'execucoes';

    protected static ?string $title = 'Histórico de execuções';

    protected static ?string $modelLabel = 'execução';

    protected static ?string $pluralModelLabel = 'execuções';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('executadoPor'))
            ->columns([
                TextColumn::make('created_at')->label('Quando')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('origem')->label('Origem')->formatStateUsing(fn ($state): string => $state->getLabel()),
                TextColumn::make('status')->label('Situação')->badge(),
                TextColumn::make('linhas')->label('Linhas')->numeric()->description(fn (ExecucaoRelatorio $record): ?string => $record->truncado ? 'Cortado no limite' : null),
                TextColumn::make('erro')->label('Motivo')->limit(70)->tooltip(fn (ExecucaoRelatorio $record): ?string => $record->erro)->placeholder('—')->wrap(),
                TextColumn::make('executadoPor.name')->label('Por')->placeholder('—'),
                TextColumn::make('expira_em')->label('Arquivo guardado até')->date('d/m/Y')->placeholder('—'),
            ])
            ->recordActions([
                Action::make('baixar')
                    ->label('Baixar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (ExecucaoRelatorio $record): bool => $record->temArquivo())
                    ->url(fn (ExecucaoRelatorio $record): string => route('relatorios.execucoes.download', $record)),
            ])
            ->defaultSort('id', 'desc')
            ->stackedOnMobile();
    }
}
