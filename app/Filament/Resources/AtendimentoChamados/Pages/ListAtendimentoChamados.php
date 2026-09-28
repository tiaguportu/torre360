<?php

namespace App\Filament\Resources\AtendimentoChamados\Pages;

use App\Filament\Resources\AtendimentoChamados\AtendimentoChamadoResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListAtendimentoChamados extends ListRecords
{
    protected static string $resource = AtendimentoChamadoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->getHelpHeaderAction(),
        ];
    }

    protected function getHelpHeaderAction(): Action
    {
        return Action::make('ajuda')
            ->label('Ajuda')
            ->icon('heroicon-o-question-mark-circle')
            ->color('gray')
            ->modalHeading('Ajuda: Central de Atendimento')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->form([
                ViewField::make('help_content')
                    ->view('filament.components.help-content')
                    ->viewData(['content' => $this->getHelpContent()]),
            ]);
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();
        $canCreate = $user->can('Create:AtendimentoChamado');

        $html = '<div class="space-y-4">';
        $html .= '<p class="text-sm text-gray-600 dark:text-gray-300">A <strong>Central de Atendimento</strong> centraliza os requerimentos, dúvidas e solicitações enviadas pelas famílias através do Portal do Aluno/Família.</p>';

        $html .= '<h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Como Operar:</h4>';
        $html .= '<ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-300 space-y-1">';
        $html .= '<li><strong>Ação Responder:</strong> Clique em "Responder" na linha do chamado para digitar a resposta da escola, anexar documentos e alterar o status.</li>';
        $html .= '<li><strong>Triagem por Setores:</strong> Utilize os filtros superiores para separar chamados da Secretaria, Financeiro, Coordenação Pedagógica ou Ambulatório.</li>';

        if ($canCreate) {
            $html .= '<li><strong>Registrar Chamado Presencial:</strong> Você também pode abrir um chamado em nome do pai/mãe atendido presencialmente ou por telefone.</li>';
        }

        $html .= '</ul>';
        $html .= '</div>';

        return $html;
    }
}
