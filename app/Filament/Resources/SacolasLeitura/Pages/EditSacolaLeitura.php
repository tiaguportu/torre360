<?php

namespace App\Filament\Resources\SacolasLeitura\Pages;

use App\Filament\Resources\SacolasLeitura\SacolaLeituraResource;
use App\Models\VideoTutorial;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\EditRecord;

class EditSacolaLeitura extends EditRecord
{
    protected static string $resource = SacolaLeituraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('gerenciar')
                ->label('Gerenciar / Bipar Livros')
                ->icon('heroicon-o-shopping-bag')
                ->color('primary')
                ->url(fn (): string => SacolaLeituraResource::getUrl('gerenciar', ['record' => $this->record])),

            DeleteAction::make(),

            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Editar Sacola de Leitura')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                            'video' => VideoTutorial::query()->ativo()->where('chave_pagina', 'sacolas-leitura-edit')->first(),
                        ]),
                ]),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return SacolaLeituraResource::getUrl('gerenciar', ['record' => $this->record]);
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();

        $html = '<div class="space-y-3 text-sm text-gray-600 dark:text-gray-300">';
        $html .= '<p>Esta página permite alterar as informações cadastrais da <strong>Sacola de Leitura</strong>, como turma de destino, professor responsável, datas de retirada e previsão de devolução.</p>';

        $html .= '<div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">';
        $html .= '<h4 class="font-semibold text-gray-900 dark:text-white mb-1">🧺 Ações Rápidas:</h4>';
        $html .= '<ul class="list-disc pl-4 space-y-1 text-xs">';
        $html .= '<li><strong>Gerenciar / Bipar Livros:</strong> Acessa o balcão de bipagem rápida para inclusão e devolução de exemplares da sacola.</li>';
        $html .= '<li><strong>Salvar alterações:</strong> Atualiza os dados cadastrais da sacola e retorna ao painel de gestão.</li>';
        $html .= '</ul>';
        $html .= '</div>';

        if ($user?->can('Update:SacolaLeitura')) {
            $html .= '<p class="text-xs text-emerald-600 dark:text-emerald-400">✓ Você possui permissão para editar os dados desta sacola.</p>';
        }

        if ($user?->can('Delete:SacolaLeitura')) {
            $html .= '<p class="text-xs text-amber-600 dark:text-amber-400">✓ Você possui permissão para excluir esta sacola (os livros pendentes retornarão ao acervo).</p>';
        }

        $html .= '</div>';

        return $html;
    }
}
