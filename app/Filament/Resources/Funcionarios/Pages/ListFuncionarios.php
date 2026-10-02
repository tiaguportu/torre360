<?php

namespace App\Filament\Resources\Funcionarios\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\Funcionarios\FuncionarioResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFuncionarios extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = FuncionarioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaAction('Funcionários', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('🧑‍💼', 'Funcionários', 'Cadastro de funcionários, contratos de trabalho e férias.')
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Listagem', 'Veja nome, cargo, regime, unidade e situação (ativo/desligado) de cada funcionário.'],
                $user->can('Create:Funcionario') ? ['🆕', 'Novo funcionário', 'Cadastre o funcionário vinculando a uma pessoa já existente.'] : null,
                $user->can('Update:Funcionario') ? ['✏️', 'Editar', 'Abra o funcionário para gerenciar contratos de trabalho e períodos de férias.'] : null,
            ])
            ->dica('O salário e o histórico de contratos ficam na aba "Contratos de Trabalho" dentro do cadastro do funcionário.');
    }
}
