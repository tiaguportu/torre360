<?php

namespace App\Filament\Resources\BolsaConcedidas\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\BolsaConcedidas\BolsaConcedidaResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBolsaConcedidas extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = BolsaConcedidaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaAction('Bolsas Concedidas', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('🎁', 'Bolsas Concedidas', 'Bolsas e descontos recorrentes concedidos a alunos matriculados.')
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Listagem', 'Veja aluno, tipo de bolsa, percentual, status e vigência.'],
                $user->can('Create:BolsaConcedida') ? ['🆕', 'Nova concessão', 'Solicite uma bolsa para um aluno — se o tipo não exigir aprovação, já nasce aprovada.'] : null,
                $user->can('Update:BolsaConcedida') ? ['✅', 'Aprovar / Recusar', 'Para bolsas que exigem aprovação, aprove ou recuse com um motivo.'] : null,
            ])
            ->dica('O percentual da bolsa aprovada e vigente é aplicado automaticamente em cada fatura gerada para o contrato do aluno — não precisa lançar o desconto à mão.');
    }
}
