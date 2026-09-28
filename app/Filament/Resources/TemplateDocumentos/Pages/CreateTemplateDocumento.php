<?php

namespace App\Filament\Resources\TemplateDocumentos\Pages;

use App\Filament\Resources\TemplateDocumentos\TemplateDocumentoResource;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\CreateRecord;

class CreateTemplateDocumento extends CreateRecord
{
    protected static string $resource = TemplateDocumentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Criar Modelo de Documento')
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
        $html = '<p>Preencha os dados para criar um novo modelo de documento oficial da secretaria.</p>';
        $html .= '<h4>Dicas de Preenchimento:</h4>';
        $html .= '<ul>';
        $html .= '<li><strong>Nome:</strong> Dê um nome claro para identificação interna e seleção no Portal (ex: "Declaração de Matrícula Regular").</li>';
        $html .= '<li><strong>Validade em Dias:</strong> Define quantos dias a declaração emitida permanecerá com status válido na consulta pública do QR Code (padrão 30 dias).</li>';
        $html .= '<li><strong>Macros do Texto:</strong> Utilize as variáveis entre chaves duplas como <code>{{ALUNO_NOME}}</code>, <code>{{ALUNO_CPF}}</code> e <code>{{SERIE_NOME}}</code> no texto. Elas serão substituídas automaticamente pelos dados do aluno no momento da emissão.</li>';
        $html .= '</ul>';

        return $html;
    }
}
