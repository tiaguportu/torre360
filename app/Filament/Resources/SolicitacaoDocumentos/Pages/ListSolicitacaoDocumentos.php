<?php

namespace App\Filament\Resources\SolicitacaoDocumentos\Pages;

use App\Filament\Resources\SolicitacaoDocumentos\SolicitacaoDocumentoResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListSolicitacaoDocumentos extends ListRecords
{
    protected static string $resource = SolicitacaoDocumentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Emitir Documento'),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Gestão de Documentos e Protocolos')
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

        $canCreate = $user->can('Create:SolicitacaoDocumento');
        $canUpdate = $user->can('Update:SolicitacaoDocumento');

        $html = '<p>Nesta página a secretaria gerencia todas as <strong>solicitações de documentos e protocolos</strong> gerados no sistema.</p>';
        $html .= '<h4>Fluxo de Emissão Digital com QR Code:</h4>';
        $html .= '<ol>';
        $html .= '<li><strong>Solicitações via Portal:</strong> Pais e estudantes podem requerer documentos (como Declaração de Matrícula ou Frequência) diretamente pelo Portal da Família.</li>';
        $html .= '<li><strong>Emissão Direta pela Secretaria:</strong> Clique no botão <em>"Emitir Documento"</em> para gerar imediatamente uma certidão para qualquer estudante ativo.</li>';
        $html .= '<li><strong>Ação "Emitir PDF":</strong> Gera o arquivo PDF timbrado oficial com substituição de variáveis e estampa o carimbo de autenticidade com QR Code no rodapé.</li>';
        $html .= '<li><strong>Ação "Conferir QR Code":</strong> Abre a página pública de validação que qualquer órgão ou terceiro pode acessar ao escanear o documento.</li>';
        $html .= '</ol>';

        $html .= '<h4>Permissões do seu Usuário:</h4>';
        $html .= '<ul>';
        if ($canCreate) {
            $html .= '<li>✓ Você pode emitir novos documentos oficiais.</li>';
        }
        if ($canUpdate) {
            $html .= '<li>✓ Você pode processar, alterar a situação e anexar justificativas nas solicitações.</li>';
        }
        $html .= '</ul>';

        return $html;
    }
}
