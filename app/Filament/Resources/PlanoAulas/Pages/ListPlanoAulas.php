<?php

namespace App\Filament\Resources\PlanoAulas\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\PlanoAulas\PlanoAulaResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPlanoAulas extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = PlanoAulaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaAction('Planos de Aula', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('🗒️', 'Planos de Aula', 'Planejamento das aulas por turma e disciplina.')
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Listagem', 'Veja turma, disciplina, professor, data prevista, objetivos e se o plano já foi executado.'],
                $user->can('Create:PlanoAula') ? ['🆕', 'Novo Plano', 'Planeje uma aula com objetivos e data prevista.'] : null,
                $user->can('Update:PlanoAula') ? ['✏️', 'Editar', 'Altere planos que ainda não foram executados.'] : null,
                ['▶️', 'Executar', 'Transforma o plano em aula real no diário de classe.'],
                ['🔎', 'Filtros', 'Filtre por turma e por planos executados ou pendentes.'],
            ])
            ->alerta('Um plano executado não pode mais ser editado nem executado de novo: o registro do diário passa a ser a fonte da verdade.')
            ->dica('Professores veem apenas os planos das suas turmas; coordenação e administração veem todos.');
    }
}
