<?php

namespace App\Filament\Resources\SubstituicaoProfessors\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\SubstituicaoProfessors\SubstituicaoProfessorResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSubstituicaoProfessors extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = SubstituicaoProfessorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaAction('Substituições de Professor', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        return HelpContent::make('🔄', 'Substituições de Professor', 'Histórico de professores substitutos por turma/disciplina.')
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Listagem', 'Veja turma, disciplina, professor titular e substituto, e o período da substituição.'],
                ['🆕', 'Nova substituição', 'Registre quando um professor substituto assume temporariamente a turma.'],
            ])
            ->dica('Deixe "Fim" em branco enquanto a substituição ainda estiver em andamento.');
    }
}
