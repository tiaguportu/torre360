<?php

namespace App\Filament\Resources\DocumentoInseridos\Pages;

use App\Filament\Resources\DocumentoInseridos\DocumentoInseridoResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListDocumentoInseridos extends ListRecords
{
    protected static string $resource = DocumentoInseridoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Auditoria de Documentos')
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

        $canCreate = $user->can('Create:DocumentoInserido');
        $canUpdate = $user->can('Update:DocumentoInserido');

        $html = '<p>Nesta página você faz a auditoria e gestão de todos os documentos enviados pelos alunos/responsáveis.</p>';
        $html .= '<h3>O que você pode fazer?</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Auditoria:</strong> Visualize o arquivo enviado e verifique se as informações estão corretas.</li>';

        if ($canUpdate) {
            $html .= '<li><strong>Validar/Rejeitar:</strong> Altere o status do documento. Se rejeitar, insira uma observação para que o responsável saiba o motivo.</li>';
        }

        $html .= '<li><strong>Filtros:</strong> Use os filtros para encontrar rapidamente documentos com status "Pendente" ou "Em Análise".</li>';
        $html .= '</ul>';

        return $html;
    }
}
