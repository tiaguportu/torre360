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

        $html = '<p>Nesta página você configura quais tipos de documentos o sistema deve exigir ou permitir o envio.</p>';
        $html .= '<h3>O que você pode fazer?</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Definição de Regras:</strong> Configure se um documento é obrigatório, se gera pendência de matrícula e para quais cursos/turmas ele se aplica.</li>';
        
        if ($canCreate) {
            $html .= '<li><strong>Novo Tipo:</strong> Crie uma nova categoria de documento (ex: RG, CPF, Histórico Escolar).</li>';
        }

        if ($canUpdate) {
            $html .= '<li><strong>Modelos:</strong> Você pode anexar um arquivo PDF ou um link que servirá de modelo para o aluno baixar e preencher.</li>';
            $html .= '<li><strong>Editar:</strong> Altere as regras de obrigatoriedade ou visibilidade do documento.</li>';
        }

        $html .= '<li><strong>Importante:</strong> Alterar a obrigatoriedade aqui impactará imediatamente o status de "Pendência" de todas as matrículas ativas.</li>';
        $html .= '</ul>';

        return $html;
    }
}
