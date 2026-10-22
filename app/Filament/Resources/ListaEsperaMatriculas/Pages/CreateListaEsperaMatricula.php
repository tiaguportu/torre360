<?php

namespace App\Filament\Resources\ListaEsperaMatriculas\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\ListaEsperaMatriculas\ListaEsperaMatriculaResource;
use App\Support\HelpContent;
use Filament\Resources\Pages\CreateRecord;

class CreateListaEsperaMatricula extends CreateRecord
{
    use HasAjudaAction;

    protected static string $resource = ListaEsperaMatriculaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['criado_por_user_id'] = auth()->id();

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Adicionar à Lista de Espera', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('⏳', 'Adicionar à Lista de Espera', 'Registro de interessado aguardando liberação de vaga em turma ou série.')
            ->secao('🎯 Funcionalidades Disponíveis', array_filter([
                ['👤', 'Candidato e Responsável', 'Preencha os dados do estudante e os contatos da família.'],
                ['🎯', 'Série e Período', 'Indique a etapa pretendida e o ano letivo de interesse.'],
            ]))
            ->secao('🔒 Permissões do Perfil', array_filter([
                $user?->can('Create:ListaEsperaMatricula') ? ['🆕', 'Cadastrar', 'Permissão para criar novos registros deste recurso.'] : null,
                $user?->can('Update:ListaEsperaMatricula') ? ['✏️', 'Editar', 'Permissão para alterar informações existentes.'] : null,
                $user?->can('Delete:ListaEsperaMatricula') ? ['🗑️', 'Excluir', 'Permissão para remover dados do sistema.'] : null,
            ]))
            ->dica('A ordem de prioridade pode ser ordenada por data de inscrição ou critérios regimentais da escola.');
    }
}
