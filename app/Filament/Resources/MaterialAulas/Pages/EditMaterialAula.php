<?php

namespace App\Filament\Resources\MaterialAulas\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\MaterialAulas\MaterialAulaResource;
use App\Support\HelpContent;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMaterialAula extends EditRecord
{
    use HasAjudaAction;

    protected static string $resource = MaterialAulaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->ajudaAction('Editar Material de Aula', $this->getHelpContent()),
            DeleteAction::make(),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('📂', 'Editar Material de Aula', 'Atualização de arquivos anexos, visibilidade e disciplina vinculada.')
            ->secao('🎯 Funcionalidades Disponíveis', array_filter([
                ['✏️', 'Conteúdo e Descrição', 'Altere as orientações e instruções de estudo aos alunos.'],
                ['👁️', 'Visibilidade', 'Ative ou desative a exibição no portal conforme o cronograma de aulas.'],
            ]))
            ->secao('🔒 Permissões do Perfil', array_filter([
                $user?->can('Create:MaterialAula') ? ['🆕', 'Cadastrar', 'Permissão para criar novos registros deste recurso.'] : null,
                $user?->can('Update:MaterialAula') ? ['✏️', 'Editar', 'Permissão para alterar informações existentes.'] : null,
                $user?->can('Delete:MaterialAula') ? ['🗑️', 'Excluir', 'Permissão para remover dados do sistema.'] : null,
            ]));
    }
}
