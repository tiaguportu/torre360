<?php

namespace App\Filament\Resources\Livros\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\Livros\LivroResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLivros extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = LivroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->ajudaAction('Biblioteca', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('📚', 'Biblioteca', 'Acervo de livros da escola.')
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Listagem', 'Veja título, autor, categoria e quantos exemplares estão disponíveis.'],
                $user->can('Create:Livro') ? ['🆕', 'Novo livro', 'Cadastre um livro e a quantidade de exemplares do acervo.'] : null,
                $user->can('Update:Livro') ? ['✏️', 'Editar', 'Atualize os dados do livro ou a quantidade de exemplares.'] : null,
            ])
            ->dica('Empréstimos e devoluções ficam na tela "Empréstimos", no menu Biblioteca.');
    }
}
