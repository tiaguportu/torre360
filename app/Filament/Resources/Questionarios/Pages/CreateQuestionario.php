<?php

namespace App\Filament\Resources\Questionarios\Pages;

use App\Filament\Resources\Questionarios\QuestionarioResource;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\CreateRecord;

class CreateQuestionario extends CreateRecord
{
    protected static string $resource = QuestionarioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->form([
                    ViewField::make('help')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                        ]),
                ])
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar'),
        ];
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();
        $html = '<div class="space-y-6">';

        $html .= '<p>Nesta página você inicia a criação de um novo questionário.</p>';
        $html .= '<p>Informe o título do questionário, descrição, período de aplicação e configurações de privacidade na aba <strong>Geral</strong>.</p>';

        $html .= '<h3 class="text-lg font-bold mt-4">⚙️ Funcionalidades Disponíveis</h3>';
        $html .= '<ul class="list-disc ml-6 space-y-1">';
        $html .= '<li>Definir o público-alvo associando unidades, cursos, séries, turmas, perfis ou usuários específicos na aba <strong>Público-Alvo</strong>.</li>';
        $html .= '<li>Definir gestores (Donos) e observadores para o questionário na aba <strong>Permissões e Acesso</strong>.</li>';
        $html .= '<li>Montar a estrutura de <strong>Blocos Temáticos</strong> e <strong>Perguntas</strong> na aba correspondente.</li>';

        if ($user && $user->can('Create:Questionario')) {
            $html .= '<li>✅ Você tem permissão para criar questionários no sistema.</li>';
        }

        $html .= '</ul>';

        $html .= '<hr class="my-4">';
        $html .= '<h3 class="text-lg font-bold">📋 Dicas de Estrutura</h3>';
        $html .= '<ul class="list-disc ml-6 space-y-1">';
        $html .= '<li>Use os botões de <strong>Clonar Bloco</strong> e <strong>Clonar Pergunta</strong> para duplicar rapidamente estruturas já configuradas.</li>';
        $html .= '<li>Configure exibições condicionais nas perguntas para criar fluxos de resposta dinâmicos.</li>';
        $html .= '</ul>';

        $html .= '</div>';

        return $html;
    }
}
