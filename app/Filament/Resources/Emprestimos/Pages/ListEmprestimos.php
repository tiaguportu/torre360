<?php

namespace App\Filament\Resources\Emprestimos\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\Emprestimos\EmprestimoResource;
use App\Support\HelpContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmprestimos extends ListRecords
{
    use HasAjudaAction;

    protected static string $resource = EmprestimoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Registrar Empréstimo'),
            $this->ajudaAction('Empréstimos', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        return HelpContent::make('🔄', 'Empréstimos', 'Controle de empréstimo e devolução de livros da biblioteca.')
            ->secao('🎯 O que você pode fazer?', [
                ['📋', 'Listagem', 'Veja livro, aluno, data de empréstimo, devolução prevista e status.'],
                ['🆕', 'Registrar Empréstimo', 'Só aparecem livros com exemplar disponível no acervo.'],
                ['✅', 'Registrar Devolução', 'Marca o empréstimo como devolvido e devolve o exemplar ao acervo.'],
            ])
            ->dica('Empréstimos com devolução prevista já vencida aparecem marcados como Atrasado todos os dias às 07h.');
    }
}
