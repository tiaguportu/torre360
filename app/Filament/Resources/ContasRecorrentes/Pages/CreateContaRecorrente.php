<?php

declare(strict_types=1);

namespace App\Filament\Resources\ContasRecorrentes\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\ContasRecorrentes\ContaRecorrenteResource;
use App\Support\HelpContent;
use Filament\Resources\Pages\CreateRecord;

class CreateContaRecorrente extends CreateRecord
{
    use HasAjudaAction;

    protected static string $resource = ContaRecorrenteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Nova despesa recorrente', HelpContent::make('🔁', 'Nova despesa recorrente', 'Preencha uma vez; as contas dos próximos meses nascem sozinhas.')
                ->secao('🎯 Como preencher', [
                    ['📅', 'Periodicidade e dia', 'Mensal vence todo mês no mesmo dia; bimestral, trimestral, semestral e anual pulam os meses.'],
                    ['📈', 'Valor estimado', 'Para contas que mudam todo mês. A conta gerada pede a revisão antes do pagamento.'],
                    ['🔔', 'Reajuste', 'Informe a data do próximo reajuste do contrato para a Mesa avisar com antecedência.'],
                ])),
        ];
    }
}
