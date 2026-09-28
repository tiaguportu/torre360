<?php

namespace App\Filament\Resources\TemplateDocumentos\Pages;

use App\Filament\Resources\TemplateDocumentos\TemplateDocumentoResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\EditRecord;

class EditTemplateDocumento extends EditRecord
{
    protected static string $resource = TemplateDocumentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Editar Modelo de Documento')
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
        $html = '<p>Edite os textos e parâmetros deste modelo de documento oficial.</p>';
        $html .= '<p><strong>Atenção:</strong> Alterar o texto deste modelo afetará apenas as novas emissões geradas a partir de agora. Documentos e PDFs já emitidos anteriormente mantêm o conteúdo original arquivado para fins de conformidade e auditoria.</p>';

        return $html;
    }
}
