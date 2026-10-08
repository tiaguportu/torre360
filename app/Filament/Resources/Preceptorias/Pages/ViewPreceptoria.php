<?php

namespace App\Filament\Resources\Preceptorias\Pages;

use App\Filament\Resources\Preceptorias\PreceptoriaResource;
use App\Models\VideoTutorial;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ViewRecord;

class ViewPreceptoria extends ViewRecord
{
    protected static string $resource = PreceptoriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('historicoLeitura')
                ->label('Histórico de Leitura')
                ->icon('heroicon-o-book-open')
                ->color('info')
                ->modalHeading('Histórico de Leitura do Estudante')
                ->modalDescription('Obras literárias e didáticas retiradas pelo aluno na biblioteca escolar.')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('leitura_content')
                        ->view('filament.components.preceptoria-historico-leitura')
                        ->viewData([
                            'preceptoria' => $this->record,
                        ]),
                ]),

            EditAction::make(),

            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Detalhes da Preceptoria')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                            'video' => VideoTutorial::query()->ativo()->where('chave_pagina', 'preceptorias-view')->first(),
                        ]),
                ]),
        ];
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();

        $html = '<div class="space-y-3 text-sm text-gray-600 dark:text-gray-300">';
        $html .= '<p>Esta página exibe os detalhes completos do agendamento de preceptoria pedagógica.</p>';

        $html .= '<div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">';
        $html .= '<h4 class="font-semibold text-gray-900 dark:text-white mb-1">📖 Histórico de Leitura do Aluno:</h4>';
        $html .= '<p>Clique no botão <strong>"Histórico de Leitura"</strong> para visualizar os livros retirados e lidos pelo estudante na biblioteca escolar, permitindo ao professor preceptor dialogar sobre os hábitos de leitura da criança.</p>';
        $html .= '</div>';

        if ($user?->can('Update:Preceptoria')) {
            $html .= '<p class="text-xs text-emerald-600 dark:text-emerald-400">✓ Você possui permissão para editar os dados deste agendamento.</p>';
        }

        $html .= '</div>';

        return $html;
    }
}
