<?php

namespace App\Filament\Resources\ListaEsperaMatriculas\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\ListaEsperaMatriculas\ListaEsperaMatriculaResource;
use App\Support\HelpContent;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditListaEsperaMatricula extends EditRecord
{
    use HasAjudaAction;

    protected static string $resource = ListaEsperaMatriculaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Editar Lista de Espera', $this->getHelpContent()),
            DeleteAction::make(),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('⏳', 'Editar Lista de Espera', 'Atualização de status, prioridade ou dados de contato da lista de espera.')
            ->secao('🎯 Funcionalidades Disponíveis', array_filter([
                ['📌', 'Status da Solicitação', 'Mude para Chamado, Matriculado ou Desistente conforme o andamento.'],
                ['📞', 'Contato', 'Mantenha telefone e e-mail atualizados para chamamento rápido.'],
            ]))
            ->secao('🔒 Permissões do Perfil', array_filter([
                $user?->can('Create:ListaEsperaMatricula') ? ['🆕', 'Cadastrar', 'Permissão para criar novos registros deste recurso.'] : null,
                $user?->can('Update:ListaEsperaMatricula') ? ['✏️', 'Editar', 'Permissão para alterar informações existentes.'] : null,
                $user?->can('Delete:ListaEsperaMatricula') ? ['🗑️', 'Excluir', 'Permissão para remover dados do sistema.'] : null,
            ]));
    }
}
