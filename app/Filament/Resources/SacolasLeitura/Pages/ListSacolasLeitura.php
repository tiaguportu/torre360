<?php

namespace App\Filament\Resources\SacolasLeitura\Pages;

use App\Filament\Resources\SacolasLeitura\SacolaLeituraResource;
use App\Models\VideoTutorial;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListSacolasLeitura extends ListRecords
{
    protected static string $resource = SacolaLeituraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nova Sacola de Leitura'),

            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Sacolas de Leitura e Empréstimos Coletivos')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                            'video' => VideoTutorial::query()->ativo()->where('chave_pagina', 'sacolas-leitura-list')->first(),
                        ]),
                ]),
        ];
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();

        $html = '<div class="space-y-3 text-sm text-gray-600 dark:text-gray-300">';
        $html .= '<p>O módulo de <strong>Sacola de Leitura</strong> atende à rotina da Educação Infantil e Ensino Fundamental I, permitindo que professores e coordenadores retirem lotes de livros para abastecer a sala de aula durante o mês ou bimestre.</p>';

        $html .= '<div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">';
        $html .= '<h4 class="font-semibold text-gray-900 dark:text-white mb-1">🧺 Funcionalidades Disponíveis:</h4>';
        $html .= '<ul class="list-disc pl-4 space-y-1 text-xs">';
        $html .= '<li><strong>Gerenciar / Bipar:</strong> Adicione livros à sacola ou faça a conferência e devolução rápida livro a livro com leitor óptico ou câmera do celular.</li>';
        $html .= '<li><strong>Ficha de Controle:</strong> Imprima a lista de livros com caixas de checagem para o professor acompanhar a leitura dos alunos na sala.</li>';
        $html .= '<li><strong>Devolver Sacola:</strong> Recoloca todos os exemplares pendentes da sacola no acervo com um único clique.</li>';
        $html .= '</ul>';
        $html .= '</div>';

        if ($user?->can('Create:SacolaLeitura')) {
            $html .= '<p class="text-xs text-emerald-600 dark:text-emerald-400">✓ Você possui permissão para registrar novas sacolas de leitura coletivas.</p>';
        }

        $html .= '</div>';

        return $html;
    }
}
