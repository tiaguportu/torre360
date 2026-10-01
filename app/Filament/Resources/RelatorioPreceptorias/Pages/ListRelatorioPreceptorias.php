<?php

namespace App\Filament\Resources\RelatorioPreceptorias\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\RelatorioPreceptorias\RelatorioPreceptoriaResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRelatorioPreceptorias extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = RelatorioPreceptoriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaAction('Relatórios de Preceptoria', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('📄', 'Relatórios de Preceptoria', 'Registros escritos de cada encontro de preceptoria.')
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Listagem', 'Veja data, horário, aluno, professor, tipo e última atualização de cada relatório.'],
                $user->can('Create:RelatorioPreceptoria') ? ['🆕', 'Novo Relatório', 'Registre o relatório de uma preceptoria realizada.'] : null,
                $user->can('Update:RelatorioPreceptoria') ? ['✏️', 'Editar', 'Complete ou corrija um relatório existente.'] : null,
            ])
            ->dica('No formulário, use "Carregar Template" e "Aplicar Template" para começar a partir de um modelo de Templates de Relatório.');
    }
}
