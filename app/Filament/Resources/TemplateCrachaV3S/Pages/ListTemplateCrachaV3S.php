<?php

namespace App\Filament\Resources\TemplateCrachaV3S\Pages;

use App\Filament\Resources\TemplateCrachaV3S\TemplateCrachaV3Resource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListTemplateCrachaV3S extends ListRecords
{
    protected static string $resource = TemplateCrachaV3Resource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Templates de Crachá V3')
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

        $canCreate = $user->can('Create:TemplateCrachaV3');
        $canUpdate = $user->can('Update:TemplateCrachaV3');
        $canDelete = $user->can('Delete:TemplateCrachaV3');

        $html = '<p>Esta página permite gerenciar os modelos de layout de crachás V3 baseados em elementos HTML interativos com o editor Moveable.</p>';
        $html .= '<h3>O que você pode fazer?</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Listagem de Modelos:</strong> Visualizar os templates já configurados com seus tamanhos e dados básicos.</li>';

        if ($canCreate) {
            $html .= '<li><strong>Novo Modelo V3:</strong> Criar um novo template inicial de crachá com o editor Moveable.</li>';
        }

        if ($canUpdate) {
            $html .= '<li><strong>Editar Canvas:</strong> Clicar na ação "Editar Canvas" na tabela para abrir o editor interativo Moveable em uma nova aba. Arraste, redimensione e rotacione elementos livremente.</li>';
        }

        if ($canDelete) {
            $html .= '<li><strong>Excluir:</strong> Remover modelos de crachás V3 do sistema.</li>';
        }

        $html .= '</ul>';
        $html .= '<p class="text-xs text-gray-500 mt-4">Nota: Os crachás utilizam marcações dinâmicas como <code>{nome}</code>, <code>{foto}</code> ou <code>{turma_nome}</code> que são substituídas no momento da geração em lote do PDF.</p>';

        return $html;
    }
}
