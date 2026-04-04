<?php

namespace App\Filament\Exports;

use App\Models\Pessoa;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class PessoaExporter extends Exporter
{
    protected static ?string $model = Pessoa::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('nome'),
            ExportColumn::make('data_nascimento'),
            ExportColumn::make('cpf'),
            ExportColumn::make('email'),
            ExportColumn::make('telefone'),
            ExportColumn::make('sexo_id'),
            ExportColumn::make('cor_raca_id'),
            ExportColumn::make('nacionalidade_id'),
            ExportColumn::make('naturalidade_id'),
            ExportColumn::make('endereco_id'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'A exportação de pessoas foi concluída e '.Number::format($export->successful_rows).' '.str('linha')->plural($export->successful_rows).' foram exportadas.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('linha')->plural($failedRowsCount).' falharam ao exportar.';
        }

        return $body;
    }
}
