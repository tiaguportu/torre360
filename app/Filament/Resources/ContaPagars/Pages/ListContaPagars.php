<?php

namespace App\Filament\Resources\ContaPagars\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\ContaPagars\ContaPagarResource;
use App\Models\ContaPagar;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListContaPagars extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = ContaPagarResource::class;

    public function mount(): void
    {
        parent::mount();

        ContaPagar::atualizarAtrasadas();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaAction('Contas a Pagar', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('🧾', 'Contas a Pagar', 'Títulos a pagar a fornecedores e prestadores, com vencimento e status.')
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Listagem', 'Veja descrição, valor, vencimento, status e fornecedor de cada título.'],
                $user->can('Create:ContaPagar') ? ['🆕', 'Nova conta', 'Cadastre um título a pagar, vinculando fornecedor, plano de contas e centro de custo.'] : null,
                $user->can('Update:ContaPagar') ? ['✏️', 'Editar', 'Atualize o título, registre a data de pagamento ou vincule a uma transação bancária.'] : null,
            ])
            ->dica('Títulos pendentes com vencimento passado são marcados como Atrasado automaticamente ao abrir esta tela.');
    }
}
