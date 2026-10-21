<?php

declare(strict_types=1);

namespace App\Filament\Resources\PontoTratamentos\Tables;

use App\Enums\StatusTratamentoPonto;
use App\Enums\TipoTratamentoPonto;
use App\Exceptions\PontoException;
use App\Models\PontoTratamento;
use App\Services\Ponto\PontoTratamentoService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PontoTratamentosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('data')->label('Dia')->date('d/m/Y')->sortable(),
                TextColumn::make('funcionario.pessoa.nome')->label('Funcionário')->weight('bold'),
                TextColumn::make('tipo')->label('Ajuste')->badge(),
                TextColumn::make('detalhe')
                    ->label('Detalhe')
                    ->state(fn (PontoTratamento $r): string => match ($r->tipo) {
                        TipoTratamentoPonto::Inclusao => 'Marcação às '.$r->momento?->format('H:i'),
                        TipoTratamentoPonto::Desconsiderar => 'Marcação NSR '.($r->registro?->nsr ?? '—'),
                        TipoTratamentoPonto::Abono => (int) $r->minutos_abono.' min',
                    }),
                TextColumn::make('status')->label('Situação')->badge(),
                TextColumn::make('solicitadoPor.name')->label('Pedido por')->toggleable(),
                TextColumn::make('motivo')->label('Motivo')->limit(60)->wrap()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('Situação')->options(StatusTratamentoPonto::class)->default(StatusTratamentoPonto::Pendente->value),
                SelectFilter::make('tipo')->label('Ajuste')->options(TipoTratamentoPonto::class),
            ])
            ->recordActions([
                Action::make('aprovar')
                    ->label('Aprovar')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('O ajuste passa a valer na apuração do dia e o banco de horas é recalculado. O registro original continua intacto.')
                    ->visible(fn (PontoTratamento $record): bool => (bool) auth()->user()?->can('aprovar', $record))
                    ->action(fn (PontoTratamento $record) => self::executar(fn () => app(PontoTratamentoService::class)->aprovar($record, auth()->user()), 'Ajuste aprovado')),
                Action::make('recusar')
                    ->label('Recusar')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (PontoTratamento $record): bool => (bool) auth()->user()?->can('recusar', $record))
                    ->action(fn (PontoTratamento $record) => self::executar(fn () => app(PontoTratamentoService::class)->recusar($record, auth()->user()), 'Ajuste recusado')),
            ]);
    }

    private static function executar(callable $acao, string $sucesso): void
    {
        try {
            $acao();
        } catch (PontoException $e) {
            Notification::make()->title('Não foi possível concluir')->body($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title($sucesso)->success()->send();
    }
}
