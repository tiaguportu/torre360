<?php

declare(strict_types=1);

namespace App\Filament\Resources\ContasRecorrentes\Tables;

use App\Enums\PeriodicidadeContaRecorrente;
use App\Models\ContaRecorrente;
use App\Services\ContaRecorrenteService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ContasRecorrentesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['fornecedor', 'centroCusto']))
            ->columns([
                TextColumn::make('descricao')->label('Despesa')->searchable()->wrap()->description(fn (ContaRecorrente $record): ?string => $record->fornecedor?->razao_social),
                TextColumn::make('periodicidade')->label('Periodicidade')->badge()->color('gray'),
                TextColumn::make('dia_vencimento')->label('Dia')->alignCenter(),
                TextColumn::make('valor')->label('Valor')->money('BRL')->description(fn (ContaRecorrente $record): ?string => $record->valor_estimado ? 'estimado' : null),
                TextColumn::make('proximo_vencimento')
                    ->label('Próximo vencimento')
                    ->state(function (ContaRecorrente $record): ?string {
                        if (! $record->is_active) {
                            return null;
                        }

                        $proximo = app(ContaRecorrenteService::class)->vencimentos($record, today(), today()->addYears(2))[0] ?? null;

                        return $proximo?->format('d/m/Y');
                    })
                    ->placeholder('—'),
                TextColumn::make('proximo_reajuste_em')
                    ->label('Reajuste')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->color(fn (ContaRecorrente $record): ?string => $record->proximo_reajuste_em && $record->proximo_reajuste_em->lte(today()->addDays((int) config('financeiro.recorrentes.aviso_reajuste_dias', 30))) ? 'danger' : null),
                IconColumn::make('is_active')->label('Ativa')->boolean()->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('periodicidade')->label('Periodicidade')->options(PeriodicidadeContaRecorrente::class),
                TernaryFilter::make('is_active')->label('Ativa')->default(true),
            ])
            ->recordActions([
                self::reajuste(),
                EditAction::make(),
            ])
            ->defaultSort('descricao')
            ->stackedOnMobile();
    }

    /**
     * Registra o reajuste do contrato: novo valor, próxima data, e as contas abertas que seguem no valor antigo são atualizadas.
     */
    public static function reajuste(): Action
    {
        return Action::make('reajustar')
            ->label('Registrar reajuste')
            ->icon('heroicon-o-arrow-trending-up')
            ->color('warning')
            ->authorize('reajustar')
            ->visible(fn (ContaRecorrente $record): bool => $record->is_active)
            ->modalHeading('Registrar o reajuste do contrato')
            ->modalDescription('O valor novo vale para as próximas contas. As contas abertas de hoje em diante que ainda estão no valor antigo são atualizadas; as que alguém editou à mão ficam como estão.')
            ->schema([
                TextInput::make('valor')->label('Novo valor (R$)')->numeric()->minValue(0.01)->required()->prefix('R$'),
                DatePicker::make('proximo_reajuste_em')->label('Próximo reajuste (se não informar, daqui a um ano)')->native(false)->minDate(today()->addDay()),
            ])
            ->fillForm(fn (ContaRecorrente $record): array => ['valor' => (float) $record->valor])
            ->action(function (array $data, ContaRecorrente $record): void {
                try {
                    $atualizadas = app(ContaRecorrenteService::class)->registrarReajuste($record, (float) $data['valor'], filled($data['proximo_reajuste_em'] ?? null) ? Carbon::parse($data['proximo_reajuste_em']) : null, auth()->user());
                } catch (\DomainException $e) {
                    Notification::make()->title('Reajuste recusado')->body($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Reajuste registrado')->body($atualizadas === 1 ? '1 conta aberta foi atualizada.' : "{$atualizadas} contas abertas foram atualizadas.")->success()->send();
            });
    }
}
