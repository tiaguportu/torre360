<?php

namespace App\Filament\Resources\RegistroRotinaDiarias\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\RegistroRotinaDiarias\RegistroRotinaDiariaResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRegistroRotinaDiarias extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = RegistroRotinaDiariaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaAction('Agenda Diária', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('🌞', 'Agenda Diária', 'Registro do dia a dia da criança (Educação Infantil): humor, soneca, refeições e atividades.')
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Listagem', 'Veja aluno, turma, data, humor e quantas refeições foram registradas.'],
                $user->can('Create:RegistroRotinaDiaria') ? ['🆕', 'Novo registro', 'Um por aluno por dia: humor, horário de soneca, refeições (uma linha por refeição) e atividades.'] : null,
            ])
            ->dica('Cada aluno só pode ter um registro por data — editar o já existente em vez de criar outro para o mesmo dia.');
    }
}
