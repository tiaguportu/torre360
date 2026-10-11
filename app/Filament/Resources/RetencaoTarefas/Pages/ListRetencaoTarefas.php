<?php

namespace App\Filament\Resources\RetencaoTarefas\Pages;

use App\Filament\Resources\RetencaoTarefas\RetencaoTarefaResource;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListRetencaoTarefas extends ListRecords
{
    protected static string $resource = RetencaoTarefaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData(['content' => $this->getHelpContent()]),
                ])
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar'),
        ];
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();
        if (! $user || ! $user->can('ViewAny:RetencaoTarefa')) {
            return '<p>Você não tem permissão para visualizar o auxílio desta página.</p>';
        }

        $canUpdate = $user->can('Update:RetencaoTarefa');

        $html = '
            <div class="space-y-3 text-sm">
                <h3 class="font-bold text-base">Playbook de Retenção Escolar</h3>
                <p>Esta tela lista os estudantes com score de risco de evasão acima do corte configurado, conectando diagnósticos pedagógicos, financeiros e de frequência ao fluxo de rematrícula.</p>
                <div class="space-y-1">
                    <p><strong>Funcionalidades disponíveis:</strong></p>
                    <ul class="list-disc pl-5 space-y-1">
                        <li><strong>Identificação de Risco:</strong> Badges coloridos indicam o score de risco (amarelo ou vermelho).</li>
                        <li><strong>Fluxo de Rematrícula:</strong> Exibe se a família já iniciou ou confirmou a rematrícula no período vigente.</li>';

        if ($canUpdate) {
            $html .= '
                        <li><strong>Iniciar Acompanhamento:</strong> Assume a tarefa e altera a situação para "Em Andamento".</li>
                        <li><strong>Concluir Acompanhamento:</strong> Registra as medidas tomadas e o desfecho da retenção.</li>';
        }

        $html .= '
                    </ul>
                </div>
            </div>
        ';

        return $html;
    }
}
