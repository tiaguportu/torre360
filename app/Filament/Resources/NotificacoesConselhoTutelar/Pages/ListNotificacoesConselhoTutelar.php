<?php

namespace App\Filament\Resources\NotificacoesConselhoTutelar\Pages;

use App\Filament\Resources\NotificacoesConselhoTutelar\NotificacaoConselhoTutelarResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListNotificacoesConselhoTutelar extends ListRecords
{
    protected static string $resource = NotificacaoConselhoTutelarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Notificações ao Conselho Tutelar (FICAI)')
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
        $activeRole = session('active_role');

        $canCreate = $user ? $user->can('Create:NotificacaoConselhoTutelar') : false;
        $canDelete = $user ? $user->can('Delete:NotificacaoConselhoTutelar') : false;

        $html = '<p>Esta tela controla as comunicações obrigatórias de infrequência escolar grave ao <strong>Conselho Tutelar</strong>, conforme determina o <strong>Art. 12, VIII da LDB</strong> (Lei nº 9.394/1996, redação dada pela Lei nº 13.803/2019) e o <strong>Art. 56, II do ECA</strong> (Lei nº 8.069/1990).</p>';
        $html .= '<h3>Diretrizes e Ações:</h3><ul>';
        $html .= '<li><strong>Gatilho Legal da LDB:</strong> Alunos com faltas acumuladas acima de 30% do limite permitido em lei (7,5% do total de aulas previstas no ano letivo) devem ter o processo instaurado.</li>';
        $html .= '<li><strong>Esgotamento dos Recursos Escolares (ECA):</strong> A escola deve comprovar tentativas de contato com a família (ligação, WhatsApp, reunião) antes de encaminhar o ofício. Use o botão <em>"Registrar Tentativa"</em> na linha da tabela.</li>';
        $html .= '<li><strong>Geração do Ofício (FICAI):</strong> Clique em <em>"Gerar FICAI / Ofício"</em> para visualizar e copiar a minuta oficial com dados consolidados para o Conselho Tutelar.</li>';
        $html .= '<li><strong>Protocolo:</strong> Após a entrega física ou eletrônica ao Conselho, utilize <em>"Protocolar Envio"</em> para registrar o número do protocolo.</li>';

        if ($canCreate) {
            $html .= '<li><strong>Novo Processo:</strong> Você pode iniciar uma notificação manual clicando em <em>"Novo Notificação ao Conselho Tutelar"</em>.</li>';
        }

        if ($canDelete) {
            $html .= '<li><strong>Exclusão:</strong> Registros indevidos podem ser excluídos por usuários autorizados.</li>';
        }

        $html .= '</ul>';

        if ($activeRole) {
            $html .= "<p class='text-xs text-gray-500 mt-2'>Papel ativo atual: <strong>{$activeRole}</strong></p>";
        }

        return $html;
    }
}
