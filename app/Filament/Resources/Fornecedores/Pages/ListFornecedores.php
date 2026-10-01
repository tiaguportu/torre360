<?php

namespace App\Filament\Resources\Fornecedores\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\Fornecedores\FornecedorResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFornecedores extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = FornecedorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaAction('Fornecedores', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('🏢', 'Fornecedores', 'Empresas e prestadores com quem a instituição negocia.')
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Listagem', 'Veja razão social, CNPJ, e-mail e telefone de cada fornecedor.'],
                $user->can('Create:Fornecedor') ? ['🆕', 'Novo Fornecedor', 'Cadastre os dados da empresa.'] : null,
                $user->can('Update:Fornecedor') ? ['✏️', 'Editar', 'Atualize dados de contato ou documentos do fornecedor.'] : null,
            ])
            ->dica('Os fornecedores cadastrados aqui podem ser vinculados às transações bancárias na conciliação.');
    }
}
