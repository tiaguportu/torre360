<?php

namespace App\Filament\Resources\MaterialAulas\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\MaterialAulas\MaterialAulaResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMaterialAulas extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = MaterialAulaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaAction('Materiais de Aula', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('📁', 'Materiais de Aula', 'Apostilas, vídeo-aulas e links para os alunos estudarem fora do horário de aula.')
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Listagem', 'Veja título, turma, disciplina, tipo e se está visível para os alunos.'],
                $user->can('Create:MaterialAula') ? ['🆕', 'Novo material', 'Publique uma apostila (arquivo), vídeo-aula (link) ou link externo.'] : null,
                $user->can('Update:MaterialAula') ? ['✏️', 'Editar', 'Ajuste o conteúdo ou oculte um material sem apagar.'] : null,
            ])
            ->dica('Materiais com "Visível" desmarcado ficam salvos mas não aparecem para o aluno no Portal — útil para preparar com antecedência.');
    }
}
