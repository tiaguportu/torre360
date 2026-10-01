<?php

namespace App\Filament\Resources\CicloPreceptorias\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\CicloPreceptorias\CicloPreceptoriaResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCicloPreceptorias extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = CicloPreceptoriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaAction('Ciclos de Preceptoria', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('🔄', 'Ciclos de Preceptoria', 'Períodos em que as preceptorias são organizadas.')
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Listagem', 'Veja nome, período letivo, datas de início e fim e quantas preceptorias cada ciclo reúne.'],
                $user->can('Create:CicloPreceptoria') ? ['🆕', 'Novo Ciclo', 'Defina o nome, o período letivo e as datas do ciclo.'] : null,
                $user->can('Update:CicloPreceptoria') ? ['✏️', 'Editar', 'Ajuste o nome ou as datas do ciclo.'] : null,
            ])
            ->dica('Crie o ciclo antes de agendar as preceptorias do período.');
    }
}
