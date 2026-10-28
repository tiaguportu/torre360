<?php

declare(strict_types=1);

namespace App\Filament\Exports;

use App\Enums\StatusFatura;
use App\Models\Fatura;
use App\Support\CsvSanitizer;
use App\Support\FaturaImportParser;
use Carbon\Carbon;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class FaturaExporter extends Exporter
{
    protected static ?string $model = Fatura::class;

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['itens', 'transacoes', 'contrato.matricula.pessoa']);
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('Nº'),

            ExportColumn::make('aluno')
                ->label('Aluno')
                ->state(fn (Fatura $record): string => (string) ($record->contrato?->matricula?->pessoa?->nome ?? ''))
                ->formatStateUsing(fn ($state) => CsvSanitizer::sanitize($state)),

            ExportColumn::make('vencimento')
                ->label('Vencimento')
                ->state(fn (Fatura $record): string => $record->vencimento ? Carbon::parse($record->vencimento)->format('d/m/Y') : ''),

            ExportColumn::make('descricao')
                ->label('Descrição')
                ->state(fn (Fatura $record): string => $record->itens->pluck('descricao')->filter(fn ($v) => filled($v))->join(' | '))
                ->formatStateUsing(fn ($state) => CsvSanitizer::sanitize($state)),

            ExportColumn::make('valor')
                ->label('Valor')
                ->state(fn (Fatura $record): string => number_format((float) $record->valor_bruto, 2, '.', '')),

            ExportColumn::make('desconto')
                ->label('Desconto')
                ->state(fn (Fatura $record): string => $record->itens->count() === 1 ? number_format((float) $record->itens->first()->desconto, 2, '.', '') : ''),

            ExportColumn::make('tipo_desconto')
                ->label('Tipo de desconto')
                ->state(function (Fatura $record): string {
                    if ($record->itens->count() !== 1) {
                        return '';
                    }

                    $tipo = (string) ($record->itens->first()->tipo_desconto ?? '');

                    return FaturaImportParser::parseTipoDesconto($tipo) ?? $tipo;
                }),

            ExportColumn::make('status')
                ->label('Status')
                ->state(fn (Fatura $record): string => $record->status instanceof StatusFatura ? $record->status->getLabel() : ($record->status ? (StatusFatura::tryFrom($record->status)?->getLabel() ?? (string) $record->status) : '')),

            ExportColumn::make('valor_a_pagar')
                ->label('Valor a pagar')
                ->state(fn (Fatura $record): string => number_format((float) $record->valor, 2, '.', '')),

            ExportColumn::make('valor_pago')
                ->label('Valor pago')
                ->state(fn (Fatura $record): string => number_format((float) $record->valor_pago, 2, '.', '')),

            ExportColumn::make('saldo')
                ->label('Saldo')
                ->state(fn (Fatura $record): string => number_format((float) $record->valor_restante, 2, '.', '')),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'A exportação de faturas foi concluída e '.Number::format($export->successful_rows).' '.str('linha')->plural($export->successful_rows).' foram exportadas.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('linha')->plural($failedRowsCount).' falharam ao exportar.';
        }

        return $body;
    }
}
