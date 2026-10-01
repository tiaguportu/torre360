<?php

namespace App\Filament\Resources\TemplateRelatorioPreceptorias\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\TemplateRelatorioPreceptorias\TemplateRelatorioPreceptoriaResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTemplateRelatorioPreceptorias extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = TemplateRelatorioPreceptoriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaAction('Templates de Relatório de Preceptoria', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('🧩', 'Templates de Relatório', 'Modelos que padronizam os relatórios de preceptoria.')
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Listagem', 'Veja o nome e as datas de criação e atualização de cada modelo.'],
                $user->can('Create:TemplateRelatorioPreceptoria') ? ['🆕', 'Novo Template', 'Crie um modelo de relatório reutilizável.'] : null,
                $user->can('Update:TemplateRelatorioPreceptoria') ? ['✏️', 'Editar', 'Atualize a estrutura de um modelo existente.'] : null,
            ])
            ->dica('Ajustar um template não altera relatórios que já foram escritos.');
    }
}
