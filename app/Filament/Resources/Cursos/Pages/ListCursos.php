<?php

namespace App\Filament\Resources\Cursos\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\Cursos\CursoResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCursos extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = CursoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaAction('Gestão de Cursos', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('🎓', 'Gestão de Cursos', 'Gerencie os cursos oferecidos pela instituição.')
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Listagem', 'Visualize todos os cursos, seus nomes internos e externos.'],
                $user->can('Create:Curso') ? ['🆕', 'Novo Curso', 'Cadastre um novo curso no sistema.'] : null,
                $user->can('Update:Curso') ? ['✏️', 'Editar', 'Altere descrições, nomes e configurações gerais do curso.'] : null,
                ['🏗️', 'Estrutura', 'Os cursos são a base para a criação de Séries e Turmas.'],
            ])
            ->dica('Cadastre o curso primeiro: Séries e Turmas dependem dele.');
    }
}
