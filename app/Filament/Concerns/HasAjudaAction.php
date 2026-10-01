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
    /**
     * Ajuda padrão para cadastros simples (listagem + novo + editar).
     * Novo/Editar só aparecem se o usuário tiver `Create:<modelo>` / `Update:<modelo>`.
     *
     * @param  string  $modelo  nome do modelo nas permissões Shield (ex: 'Banco')
     * @param  array<int, array<int, string>|null>  $extras  itens adicionais da seção principal
     */
    protected function ajudaCadastro(
        string $emoji,
        string $titulo,
        string $resumo,
        string $modelo,
        string $listagem,
        string $novo,
        string $editar,
        array $extras = [],
        ?string $dica = null,
    ): Action {
        $user = auth()->user();

        $conteudo = HelpContent::make($emoji, $titulo, $resumo)
            ->secao('🎯 O que você pode fazer?', array_merge([
                ['📋', 'Listagem', $listagem],
                $user?->can('Create:'.$modelo) ? ['🆕', 'Novo registro', $novo] : null,
                $user?->can('Update:'.$modelo) ? ['✏️', 'Editar', $editar] : null,
            ], $extras));

        if ($dica) {
            $conteudo->dica($dica);
        }

        return $this->ajudaAction($titulo, $conteudo);
    }

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
