<?php

namespace App\Filament\Resources\BemPatrimonials\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\BemPatrimonials\BemPatrimonialResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBemPatrimonials extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = BemPatrimonialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaAction('Bens Patrimoniais', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('📦', 'Bens Patrimoniais', 'Controle de computadores, mobiliário e demais bens da escola.')
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Listagem', 'Veja descrição, número de patrimônio, categoria, unidade/sala e status de cada bem.'],
                $user->can('Create:BemPatrimonial') ? ['🆕', 'Novo bem', 'Cadastre um bem informando onde ele está alocado.'] : null,
                $user->can('Update:BemPatrimonial') ? ['🔄', 'Transferir', 'Mude o bem de unidade/sala, mantendo o histórico.'] : null,
                $user->can('Update:BemPatrimonial') ? ['🔧', 'Mudar Status', 'Marque como em manutenção ou baixado.'] : null,
            ])
            ->dica('O histórico de transferências e mudanças de status fica na aba "Movimentações" dentro do cadastro do bem.');
    }
}
