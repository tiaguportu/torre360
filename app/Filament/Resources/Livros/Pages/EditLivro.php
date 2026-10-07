<?php

namespace App\Filament\Resources\Livros\Pages;

use App\Filament\Resources\Livros\LivroResource;
use App\Models\VideoTutorial;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\EditRecord;

class EditLivro extends EditRecord
{
    protected static string $resource = LivroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Editar Livro')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                            'video' => VideoTutorial::query()->ativo()->where('chave_pagina', 'livros-edit')->first(),
                        ]),
                ]),
        ];
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();

        $html = '<div class="space-y-3 text-sm text-gray-600 dark:text-gray-300">';
        $html .= '<p>Esta página permite editar as informações do livro cadastrado no acervo da biblioteca.</p>';

        $html .= '<div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">';
        $html .= '<h4 class="font-semibold text-gray-900 dark:text-white mb-1">📸 Foto da Capa:</h4>';
        $html .= '<p>Você pode atualizar ou substituir a foto da capa enviando uma nova imagem ou ajustando os dados existentes.</p>';
        $html .= '</div>';

        $html .= '<div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">';
        $html .= '<h4 class="font-semibold text-gray-900 dark:text-white mb-1">📦 Exemplares:</h4>';
        $html .= '<p>Mantenha atualizadas as quantidades de exemplares totais e disponíveis para garantir o controle fidedigno dos empréstimos.</p>';
        $html .= '</div>';

        if ($user?->can('Delete:Livro')) {
            $html .= '<p class="text-xs text-red-500 dark:text-red-400">🗑️ Você possui permissão para excluir este livro do acervo através do botão Excluir.</p>';
        }

        $html .= '</div>';

        return $html;
    }
}
