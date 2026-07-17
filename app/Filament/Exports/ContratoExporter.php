<?php

namespace App\Filament\Exports;

use App\Models\Contrato;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class ContratoExporter extends Exporter
{
    protected static ?string $model = Contrato::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('matricula_id')
                ->label('ID da Matrícula'),
            ExportColumn::make('matricula.pessoa.nome')
                ->label('Aluno (Nome)'),
            ExportColumn::make('template_contrato_id')
                ->label('ID do Template'),
            ExportColumn::make('templateContrato.nome')
                ->label('Template (Nome)'),
            ExportColumn::make('valor_total')
                ->label('Valor Total'),
            ExportColumn::make('data_aceite')
                ->label('Data de Aceite'),
            ExportColumn::make('assinafy_id')
                ->label('Assinafy ID'),
            ExportColumn::make('assinafy_status')
                ->label('Assinafy Status'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'A exportação de contratos foi concluída e '.Number::format($export->successful_rows).' '.str('linha')->plural($export->successful_rows).' foram exportadas.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('linha')->plural($failedRowsCount).' falharam ao exportar.';
        }

        return $body;
    }
}
