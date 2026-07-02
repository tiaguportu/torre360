<?php

namespace App\Filament\Resources\TemplateCrachaV2S\Pages;

use App\Filament\Resources\TemplateCrachaV2S\TemplateCrachaV2Resource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListTemplateCrachaV2S extends ListRecords
{
    protected static string $resource = TemplateCrachaV2Resource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Templates de Crachá V2')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                        ]),
                ]),
        ];
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();

        $canCreate = $user->can('Create:TemplateCrachaV2');
        $canUpdate = $user->can('Update:TemplateCrachaV2');
        $canDelete = $user->can('Delete:TemplateCrachaV2');

        $html = '<p>Esta página permite gerenciar os modelos de layout de crachás V2 baseados em SVG editável.</p>';
        $html .= '<h3>O que você pode fazer?</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Listagem de Modelos:</strong> Visualizar os templates já configurados com seus tamanhos e dados básicos.</li>';

        if ($canCreate) {
            $html .= '<li><strong>Novo Modelo V2:</strong> Criar um novo template inicial de crachá.</li>';
        }

        if ($canUpdate) {
            $html .= '<li><strong>Editar Canvas:</strong> Clicar na ação "Editar Canvas" na tabela para abrir o editor gráfico SVG-Edit em uma nova aba e desenhar livremente.</li>';
        }

        if ($canDelete) {
            $html .= '<li><strong>Excluir:</strong> Remover modelos de crachás V2 do sistema.</li>';
        }

        $html .= '</ul>';
        $html .= '<p class="text-xs text-gray-500 mt-4">Nota: Os crachás utilizam marcações vetoriais como <code>{nome}</code>, <code>{foto}</code> ou <code>{turma_nome}</code> que são substituídos no momento da geração em lote do PDF.</p>';

        return $html;
    }
}
