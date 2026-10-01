<?php

namespace App\Filament\Resources\SolicitacaoDocumentos\Pages;

use App\Filament\Resources\SolicitacaoDocumentos\SolicitacaoDocumentoResource;
use App\Models\SolicitacaoDocumento;
use App\Models\VideoTutorial;
use App\Services\DocumentoService;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateSolicitacaoDocumento extends CreateRecord
{
    protected static string $resource = SolicitacaoDocumentoResource::class;

    protected static ?string $title = 'Emitir Novo Documento Oficial';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['protocolo'] = SolicitacaoDocumento::gerarProtocolo();
        $data['codigo_verificacao'] = SolicitacaoDocumento::gerarCodigoVerificacao();
        $data['solicitado_por_user_id'] = auth()->id();
        $data['atendido_por_user_id'] = auth()->id();
        $data['data_solicitacao'] = now();
        $data['data_emissao'] = now();

        return $data;
    }

    protected function afterCreate(): void
    {
        try {
            $service = app(DocumentoService::class);
            $service->gerarPdf($this->record);

            Notification::make()
                ->title('Documento Emitido com Sucesso')
                ->body("O PDF oficial foi gerado sob o Protocolo {$this->record->protocolo}.")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Documento registrado com pendência de PDF')
                ->body('O registro foi gravado, mas ocorreu um aviso ao gerar o arquivo PDF: '.$e->getMessage())
                ->warning()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Emissão de Documento Oficial')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                            'video' => VideoTutorial::query()->ativo()->where('chave_pagina', 'solicitacao-documentos-emissao-qrcode')->first(),
                        ]),
                ]),
        ];
    }

    private function getHelpContent(): string
    {
        $html = '<p>Utilize esta tela para emitir imediatamente uma certidão ou declaração oficial para um estudante.</p>';
        $html .= '<h4>O que acontece ao salvar:</h4>';
        $html .= '<ul>';
        $html .= '<li>O sistema gera um <strong>número de protocolo sequencial</strong> e um <strong>código de verificação único</strong> (hash).</li>';
        $html .= '<li>O PDF é gerado e assinado digitalmente com o carimbo contendo o <strong>QR Code oficial</strong>.</li>';
        $html .= '<li>O documento fica disponível para download na lista e para a família no Portal do Aluno.</li>';
        $html .= '</ul>';

        return $html;
    }
}
