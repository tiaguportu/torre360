<?php

namespace App\Filament\Resources\TemplateDocumentos\Pages;

use App\Filament\Resources\TemplateDocumentos\TemplateDocumentoResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListTemplateDocumentos extends ListRecords
{
    protected static string $resource = TemplateDocumentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Modelos de Documentos Oficiais')
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

        $canCreate = $user->can('Create:TemplateDocumento');
        $canUpdate = $user->can('Update:TemplateDocumento');
        $canDelete = $user->can('Delete:TemplateDocumento');

        $html = '<p>Nesta página você gerencia os <strong>Modelos de Documentos Oficiais da Secretaria</strong> (Declaração de Matrícula, Frequência, Quitação de Débitos e Histórico Escolar).</p>';
        $html .= '<h4>Como Funciona a Emissão com QR Code:</h4>';
        $html .= '<p>Os modelos cadastrados aqui servem de base para a emissão de declarações para os estudantes. Sempre que um documento é emitido pelo sistema (seja pela secretaria ou por solicitação do aluno no Portal), o sistema substitui automaticamente as variáveis dinâmicas pelos dados reais da matrícula e anexa no rodapé um <strong>carimbo de autenticidade digital com QR Code</strong> para validação pública imediata.</p>';

        $html .= '<h4>Ações Disponíveis:</h4>';
        $html .= '<ul>';
        $html .= '<li><strong>Listagem e Busca:</strong> Consulte os modelos cadastrados, visualize o prazo de validade e o número de emissões já realizadas.</li>';

        if ($canCreate) {
            $html .= '<li><strong>Novo Modelo:</strong> Cadastre novos tipos de atestados ou declarações personalizadas com texto formatado no editor de texto.</li>';
        }

        if ($canUpdate) {
            $html .= '<li><strong>Editar Modelo:</strong> Ajuste o texto padrão, prazo de validade em dias ou ative/desative o modelo.</li>';
        }

        if ($canDelete) {
            $html .= '<li><strong>Excluir:</strong> Remova modelos que não são mais utilizados na escola.</li>';
        }

        $html .= '</ul>';

        return $html;
    }
}
