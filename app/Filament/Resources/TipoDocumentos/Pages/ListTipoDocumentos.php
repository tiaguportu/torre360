<?php

namespace App\Filament\Resources\TipoDocumentos\Pages;

use App\Filament\Resources\TipoDocumentos\TipoDocumentoResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListTipoDocumentos extends ListRecords
{
    protected static string $resource = TipoDocumentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Tipos de Documentos')
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

        $canCreate = $user->can('Create:TipoDocumento');
        $canUpdate = $user->can('Update:TipoDocumento');

        $html = '<p>Nesta página você configura as regras de exigência e visibilidade de cada tipo de documento do sistema.</p>';
        $html .= '<h3>Classificação dos Documentos</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>🔴 Obrigatório para Contrato:</strong> Documento indispensável para emitir o Contrato Escolar e ativar a matrícula. Sem ele, a matrícula é registrada como Pendente.</li>';
        $html .= '<li><strong>🟡 Obrigatório para Histórico do Aluno:</strong> Exigido para a vida acadêmica e conformidade com o MEC. Não impede a emissão do contrato.</li>';
        $html .= '<li><strong>🟢 Opcional / Complementar:</strong> Aparece no Portal da Família e nos formulários para envio facultativo (ex: laudos médicos, carteirinha de convênio).</li>';
        $html .= '<li><strong>⚪ Uso Interno da Secretaria:</strong> Restrito à secretaria e arquivo escolar (não aparece no Portal da Família).</li>';
        $html .= '</ul>';

        $html .= '<h3>O que você pode fazer?</h3>';
        $html .= '<ul>';
        if ($canCreate) {
            $html .= '<li><strong>Novo Tipo:</strong> Cadastre novos tipos de documentos com regras específicas de exigência e cursos vinculados.</li>';
        }

        if ($canUpdate) {
            $html .= '<li><strong>Modelos:</strong> Anexe arquivos PDF ou links de instruções/modelos que a família pode consultar e baixar.</li>';
            $html .= '<li><strong>Editar:</strong> Altere as regras de obrigatoriedade, cursos vinculados ou visibilidade do documento.</li>';
        }
        $html .= '</ul>';

        return $html;
    }
}
