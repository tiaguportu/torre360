<?php

namespace App\Filament\Resources\SolicitacaoDocumentos\Pages;

use App\Filament\Resources\SolicitacaoDocumentos\SolicitacaoDocumentoResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\EditRecord;

class EditSolicitacaoDocumento extends EditRecord
{
    protected static string $resource = SolicitacaoDocumentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Editar Solicitação de Documento')
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
        $html = '<p>Nesta tela você pode atualizar o status do pedido de documento, prorrogar a validade ou registrar justificativa de recusa.</p>';

        return $html;
    }
}
