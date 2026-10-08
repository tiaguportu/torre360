<?php

namespace App\Filament\Resources\Inventarios\Pages;

use App\Filament\Resources\Inventarios\InventarioResource;
use App\Models\VideoTutorial;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListInventarios extends ListRecords
{
    protected static string $resource = InventarioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Novo Inventário'),

            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Auditorias e Inventários do Acervo')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                            'video' => VideoTutorial::query()->ativo()->where('chave_pagina', 'inventarios-list')->first(),
                        ]),
                ]),
        ];
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();

        $html = '<div class="space-y-3 text-sm text-gray-600 dark:text-gray-300">';
        $html .= '<p>Esta página gerencia as sessões de auditoria física e inventário periódicas do acervo da biblioteca escolar.</p>';

        $html .= '<div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">';
        $html .= '<h4 class="font-semibold text-gray-900 dark:text-white mb-1">📋 Como funciona:</h4>';
        $html .= '<p>Crie um novo inventário para iniciar uma auditoria. Em seguida, clique em <strong>"Auditar / Bipar"</strong> para abrir a tela de conferência rápida com leitor óptico nas estantes.</p>';
        $html .= '</div>';

        if ($user?->can('Create:InventarioAcervo')) {
            $html .= '<p class="text-xs text-emerald-600 dark:text-emerald-400">✓ Você possui permissão para abrir novos inventários no acervo.</p>';
        }

        $html .= '</div>';

        return $html;
    }
}
