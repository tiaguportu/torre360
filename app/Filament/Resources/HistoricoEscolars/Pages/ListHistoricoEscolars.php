<?php

namespace App\Filament\Resources\HistoricoEscolars\Pages;

use App\Filament\Resources\HistoricoEscolars\HistoricoEscolarResource;
use App\Models\VideoTutorial;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListHistoricoEscolars extends ListRecords
{
    protected static string $resource = HistoricoEscolarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Novo Histórico Escolar'),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Histórico Escolar Oficial Multi-Ano')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                            'video' => VideoTutorial::query()->ativo()->where('chave_pagina', 'historico-escolar-multi-ano')->first(),
                        ]),
                ]),
        ];
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();

        $canCreate = $user->can('Create:HistoricoEscolar');
        $canUpdate = $user->can('Update:HistoricoEscolar');
        $canDelete = $user->can('Delete:HistoricoEscolar');

        $html = '<p>O módulo de <strong>Histórico Escolar Oficial Multi-Ano</strong> permite consolidar toda a trajetória escolar do estudante (Ensino Fundamental ou Médio) em uma matriz curricular padronizada conforme as diretrizes do MEC e da LDB.</p>';

        $html .= '<h4>Principais Funcionalidades:</h4>';
        $html .= '<ul>';
        $html .= '<li><strong>Sincronização Automática:</strong> Importa em um clique todas as séries cursadas no Torre360, suas disciplinas, cargas horárias e médias finais obtidas.</li>';
        $html .= '<li><strong>Histórico Híbrido (Interno + Externo):</strong> Permite cadastrar os anos/séries que o aluno cursou em outras escolas antes de se transferir para a instituição atual.</li>';
        $html .= '<li><strong>Emissão Oficial em PDF (A4 Paisagem):</strong> Gera o documento oficial com timbre, dados do aluno, tabela matricial de notas e faltas, dados de escolas anteriores, termo de certificação e QR Code de autenticidade pública.</li>';
        $html .= '</ul>';

        $html .= '<h4>Permissões do seu Usuário:</h4>';
        $html .= '<ul>';
        if ($canCreate) {
            $html .= '<li>✓ Você pode lavrar novos Históricos Escolares para os alunos.</li>';
        }
        if ($canUpdate) {
            $html .= '<li>✓ Você pode editar notas, anos e observações nos históricos existentes.</li>';
        }
        if ($canDelete) {
            $html .= '<li>✓ Você possui permissão para excluir históricos escolares.</li>';
        }
        $html .= '</ul>';

        return $html;
    }
}
