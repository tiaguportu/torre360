<?php

namespace App\Filament\Resources\Avaliacaos\Pages;

use App\Filament\Resources\Avaliacaos\AvaliacaoResource;
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
                        ]),
                ]),
        ];
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();

        $canCreate = $user->can('Create:Avaliacao');

        $html = '<p>Nesta página você cadastra uma nova atividade avaliativa para uma turma e disciplina.</p>';
        $html .= '<h3>O que preencher?</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Turma e Disciplina:</strong> Selecione a turma e a disciplina correspondentes à avaliação.</li>';
        $html .= '<li><strong>Etapa Avaliativa:</strong> Selecione a etapa correspondente (ex: 1º Bimestre) à avaliação.</li>';
        $html .= '<li><strong>Descrição:</strong> Insira o nome ou descrição da avaliação (ex: Prova Mensal, Trabalho de Biologia).</li>';
        $html .= '<li><strong>Peso/Nota Máxima:</strong> Defina o peso ou valor máximo que esta avaliação terá.</li>';
        $html .= '</ul>';

        $html .= '<h3>Ações e Permissões</h3>';
        $html .= '<ul>';
        if ($canCreate) {
            $html .= '<li><strong>Salvar:</strong> Clique no botão "Criar" ou "Criar e criar outro" no rodapé para registrar a avaliação.</li>';
        } else {
            $html .= '<li>Você não tem permissão para cadastrar novas avaliações.</li>';
        }
        $html .= '</ul>';

        return $html;
    }
}
