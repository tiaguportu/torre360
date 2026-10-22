<?php

namespace App\Filament\Resources\MaterialAulas\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\MaterialAulas\MaterialAulaResource;
use App\Support\HelpContent;
use Filament\Resources\Pages\CreateRecord;

class CreateMaterialAula extends CreateRecord
{
    use HasAjudaAction;

    protected static string $resource = MaterialAulaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Novo Material de Aula', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('📂', 'Novo Material de Aula', 'Publicação de conteúdos, apostilas e links pedagógicos para os alunos.')
            ->secao('🎯 Funcionalidades Disponíveis', array_filter([
                ['🎓', 'Turma e Disciplina', 'Selecione a turma e o componente curricular destinatário.'],
                ['📎', 'Anexos e Links', 'Envie arquivos em PDF ou informe links externos de apoio.'],
            ]))
            ->secao('🔒 Permissões do Perfil', array_filter([
                $user?->can('Create:MaterialAula') ? ['🆕', 'Cadastrar', 'Permissão para criar novos registros deste recurso.'] : null,
                $user?->can('Update:MaterialAula') ? ['✏️', 'Editar', 'Permissão para alterar informações existentes.'] : null,
                $user?->can('Delete:MaterialAula') ? ['🗑️', 'Excluir', 'Permissão para remover dados do sistema.'] : null,
            ]))
            ->dica('Defina se o material já deve ficar imediatamente visível para alunos e responsáveis no portal.');
    }
}
