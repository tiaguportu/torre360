<?php

namespace App\Filament\Resources\TemplateCrachas\Pages;

use App\Filament\Resources\TemplateCrachas\TemplateCrachaResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListTemplateCrachas extends ListRecords
{
    protected static string $resource = TemplateCrachaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Templates de Crachá')
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

        $canCreate = $user->can('Create:TemplateCracha');
        $canUpdate = $user->can('Update:TemplateCracha');
        $canDelete = $user->can('Delete:TemplateCracha');

        $html = '<p>Esta página permite gerenciar os modelos de layout dos crachás impressos pelo sistema.</p>';
        $html .= '<h3>O que você pode fazer?</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Listagem de Modelos:</strong> Visualizar os templates já configurados com seus tamanhos e dados.</li>';

        if ($canCreate) {
            $html .= '<li><strong>Novo Modelo:</strong> Criar um novo template e desenhar seu layout no editor interativo.</li>';
        }

        if ($canUpdate) {
            $html .= '<li><strong>Editar Layout:</strong> Abrir o editor interativo de arrastar e soltar para ajustar textos e imagens do crachá.</li>';
        }

        if ($canDelete) {
            $html .= '<li><strong>Excluir:</strong> Remover modelos de crachás que não são mais utilizados no sistema.</li>';
        }

        $html .= '</ul>';
        $html .= '<p class="text-xs text-gray-500 mt-4">Nota: Os crachás utilizam marcações como <code>{nome}</code>, <code>{profissao}</code> que serão substituídos no momento da geração em lote do PDF.</p>';

        return $html;
    }
}
