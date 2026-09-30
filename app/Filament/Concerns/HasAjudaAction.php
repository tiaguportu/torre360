<?php

namespace App\Filament\Concerns;

use App\Models\VideoTutorial;
use App\Support\HelpContent;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;

/**
 * Botão "Ajuda" padrão (ícone de interrogação) com o modal redesenhado.
 * Use em getHeaderActions(): `$this->ajudaAction('Gestão de Cursos', $conteudo)`.
 */
trait HasAjudaAction
{
    protected function ajudaAction(string $titulo, HelpContent|string $conteudo, ?string $chaveVideo = null): Action
    {
        $hero = $conteudo instanceof HelpContent
            ? ['icone' => $conteudo->icone(), 'titulo' => $conteudo->titulo(), 'resumo' => $conteudo->resumo()]
            : [];

        return Action::make('ajuda')
            ->label('Ajuda')
            ->icon('heroicon-o-question-mark-circle')
            ->color('gray')
            ->modalHeading('Ajuda: '.$titulo)
            ->modalWidth('3xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->form([
                ViewField::make('help_content')
                    ->view('filament.components.help-content')
                    ->viewData([
                        'content' => $conteudo instanceof HelpContent ? $conteudo->render() : $conteudo,
                        'video' => $chaveVideo
                            ? VideoTutorial::query()->ativo()->where('chave_pagina', $chaveVideo)->orderBy('ordem')->first()
                            : null,
                    ] + $hero),
            ]);
    }
}
