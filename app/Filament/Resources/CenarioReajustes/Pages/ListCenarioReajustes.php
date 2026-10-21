<?php

declare(strict_types=1);

namespace App\Filament\Resources\CenarioReajustes\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\CenarioReajustes\CenarioReajusteResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCenarioReajustes extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = CenarioReajusteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nova simulação'),
            $this->ajudaAction('Reajuste de Mensalidades', HelpContent::make('📈', 'Reajuste de Mensalidades', 'Simule, aprove e aplique o novo valor de cada série na campanha de rematrícula.')
                ->passos('Como fazer', [
                    'Clique em "Nova simulação": escolha a campanha de rematrícula, o percentual e o arredondamento. O sistema calcula o valor atual de cada série pela média dos contratos do período de origem.',
                    'Ajuste séries com percentual próprio ou valor atual informado à mão, na lista de séries abaixo do cenário. Confira a projeção de receita e os pontos de atenção.',
                    'Aprove o cenário. A partir daí ele não muda.',
                    'Aplique na campanha: as próximas rematrículas efetivadas usam o preço da série no novo contrato.',
                    'Baixe a lista de comunicação e registre a data em que as famílias foram avisadas.',
                ])
                ->alerta('A Lei nº 9.870/1999 (art. 1º, § 5º) pede que o valor seja divulgado ao menos 45 dias antes da data final de matrícula. O sistema avisa, mas a responsabilidade é da escola: confira com o jurídico.')
                ->dica('Contratos que já existem não são alterados. Se alguma rematrícula da campanha já foi efetivada, o cenário não pode ser aplicado (as famílias teriam preços diferentes para a mesma série).')),
        ];
    }
}
