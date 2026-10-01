<?php

namespace App\Filament\Resources\PlanoContas\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\PlanoContas\PlanoContaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPlanoContas extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = PlanoContaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaCadastro('📒', 'Plano de Contas', 'Estrutura contábil hierárquica da instituição.', 'PlanoConta',
                'Veja código, nome, tipo, conta pai e se a conta está ativa.', 'Cadastre uma conta, informando código, nome, tipo e, se houver, a conta pai.', 'Altere dados da conta ou desative-a.',
                extras: [['🌳', 'Hierarquia', 'Use a conta pai para organizar contas em níveis.']],
                dica: 'O plano de contas pode ser vinculado às transações bancárias.'),
        ];
    }
}
