<?php

namespace App\Filament\Resources\PlanilhaLeiMensalidades\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\PlanilhaLeiMensalidades\PlanilhaLeiMensalidadeResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPlanilhaLeiMensalidades extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = PlanilhaLeiMensalidadeResource::class;

    protected function getHeaderActions(): array
    {
        $user = auth()->user();

        $conteudo = HelpContent::make(
            '⚖️',
            'Planilhas de Variação de Custos (Lei 9.870/1999)',
            'Gerencie a memória de cálculo oficial de reajuste das anuidades e mensalidades escolares, garantindo respaldo jurídico perante o PROCON e órgãos de fiscalização.'
        )
            ->secao('💡 Diretrizes da Lei Federal nº 9.870/99', [
                ['📅', 'Aviso Prévio Obrigatório', 'A planilha de custos deve ser afixada em local visível com no mínimo 45 dias antes do encerramento da matrícula.'],
                ['📊', 'Critérios de Acréscimo', 'A variação deve refletir despesas com pessoal (dissídio), custeio geral e novos aprimoramentos pedagógicos.'],
                ['🏛️', 'Blindagem Legal', 'Clique em "Espelho Oficial" para gerar o demonstrativo timbrado exigido pelo Ministério Público e PROCON.'],
            ]);

        $acoes = [];

        if ($user && $user->can('Create:PlanilhaLeiMensalidade')) {
            $acoes[] = CreateAction::make();
        }

        $acoes[] = $this->ajudaAction('Planilhas da Lei 9.870/99', $conteudo);

        return $acoes;
    }
}
