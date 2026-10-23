<?php

namespace App\Filament\Resources\Avaliacaos\Pages;

use App\Filament\Resources\Avaliacaos\AvaliacaoResource;
use App\Models\VideoTutorial;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\CreateRecord;

class CreateAvaliacao extends CreateRecord
{
    protected static string $resource = AvaliacaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Cadastrar Avaliação')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                            'video' => VideoTutorial::query()->ativo()->where('chave_pagina', 'avaliacoes-notas')->orderBy('ordem')->first(),
                        ]),
                ]),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return AvaliacaoResource::getUrl('lancar-notas', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Avaliação criada com sucesso! Prossiga com o lançamento de notas.';
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();

        $canCreate = $user->can('Create:Avaliacao');

        $html = '<p>Nesta página você cadastra uma nova atividade avaliativa para uma turma e disciplina.</p>';
        $html .= '<h3>O que preencher?</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Período Letivo:</strong> Selecione o período letivo correspondente para filtrar as turmas e etapas.</li>';
        $html .= '<li><strong>Turma e Disciplina:</strong> Selecione a turma ativa e a disciplina da avaliação. O professor regente será sugerido automaticamente.</li>';
        $html .= '<li><strong>Etapa Avaliativa:</strong> Selecione a etapa correspondente (ex: 1º Bimestre).</li>';
        $html .= '<li><strong>Categoria e Datas:</strong> Escolha a categoria (Prova, Trabalho, etc.), a data prevista e o prazo limite de lançamento.</li>';
        $html .= '<li><strong>Nota Máxima e Peso:</strong> Defina o valor máximo da avaliação e o peso na etapa.</li>';
        $html .= '</ul>';

        $html .= '<h3>Fluxo Operacional Rápido</h3>';
        $html .= '<p>Ao clicar em <strong>"Criar"</strong>, você será automaticamente redirecionado para a tela de <strong>Lançamento de Notas</strong> da turma, agilizando o fluxo de trabalho.</p>';

        $html .= '<h3>Ações e Permissões</h3>';
        $html .= '<ul>';
        if ($canCreate) {
            $html .= '<li><strong>Salvar:</strong> Clique no botão "Criar" no rodapé para registrar a avaliação e abrir o lançamento de notas, ou "Criar e criar outro" para continuar cadastrando avaliações.</li>';
        } else {
            $html .= '<li>Você não tem permissão para cadastrar novas avaliações.</li>';
        }
        $html .= '</ul>';

        return $html;
    }
}
