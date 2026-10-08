<?php

namespace App\Filament\Resources\Livros\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\Livros\LivroResource;
use App\Filament\Resources\Livros\Tables\LivrosTable;
use App\Support\HelpContent;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Facades\FilamentView;

class ListLivros extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = LivroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->visualizacaoAction(LivrosTable::VISUALIZACAO_LISTA, 'Ver em lista', 'heroicon-o-list-bullet'),
            $this->visualizacaoAction(LivrosTable::VISUALIZACAO_GRADE, 'Ver em grade', 'heroicon-o-squares-2x2'),
            CreateAction::make(),
            $this->ajudaAction('Biblioteca', $this->getHelpContent()),
        ];
    }

    /**
     * A tabela é montada a cada requisição antes de qualquer ação rodar, então a
     * troca de visualização grava a preferência na sessão e recarrega a página
     * (mantendo busca, filtros e ordenação, que ficam na URL).
     */
    private function visualizacaoAction(string $modo, string $label, string $icon): Action
    {
        $ativa = fn (): bool => LivrosTable::visualizacaoAtual() === $modo;

        return Action::make('visualizacao'.ucfirst($modo))
            ->label($label)
            ->tooltip($label)
            ->icon($icon)
            ->iconButton()
            ->color(fn (): string => $ativa() ? 'primary' : 'gray')
            ->action(function () use ($modo, $ativa): void {
                if ($ativa()) {
                    return;
                }

                session([LivrosTable::SESSION_VISUALIZACAO => $modo]);

                $url = url()->previous(static::getResource()::getUrl());

                $this->redirect($url, navigate: FilamentView::hasSpaMode($url));
            });
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('📚', 'Biblioteca', 'Acervo de livros da escola.')
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Listagem', 'Veja título, autor, categoria e quantos exemplares estão disponíveis.'],
                ['🔲', 'Lista ou grade', 'Use os botões no topo para alternar entre a tabela e os cartões com a capa de cada livro. A escolha fica salva para a próxima vez.'],
                $user->can('Create:Livro') ? ['🆕', 'Novo livro', 'Cadastre um livro e a quantidade de exemplares do acervo.'] : null,
                $user->can('Update:Livro') ? ['✏️', 'Editar', 'Atualize os dados do livro ou a quantidade de exemplares.'] : null,
            ])
            ->dica('Empréstimos e devoluções ficam na tela "Empréstimos", no menu Biblioteca.');
    }
}
