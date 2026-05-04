<?php

namespace App\Filament\Resources\Pessoas\Pages;

use App\Filament\Imports\PessoaImporter;
use App\Filament\Resources\Pessoas\PessoaResource;
use App\Models\Pessoa;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListPessoas extends ListRecords
{
    protected static string $resource = PessoaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ImportAction::make()
                ->importer(PessoaImporter::class)
                ->visible(fn (): bool => auth()->user()->can('import', Pessoa::class)),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Cadastro de Pessoas')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                        ]),
                ]),
        ];
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();
        
        $canCreate = $user->can('Create:Pessoa');
        $canUpdate = $user->can('Update:Pessoa');

        $html = '<p>Este é o cadastro geral de pessoas do sistema (Alunos, Responsáveis, Professores, Funcionários).</p>';
        $html .= '<h3>O que você pode fazer?</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Busca Centralizada:</strong> Localize qualquer pessoa cadastrada por nome, CPF ou e-mail.</li>';
        
        if ($canCreate) {
            $html .= '<li><strong>Novo Cadastro:</strong> Adicione uma nova pessoa ao banco de dados.</li>';
        }

        $html .= '<li><strong>Gestão de Perfis:</strong> Dentro da edição, você pode atribuir papéis (ex: tornar uma pessoa um Professor ou Responsável).</li>';
        
        if ($canUpdate) {
            $html .= '<li><strong>Editar:</strong> Altere endereços, contatos e documentos pessoais.</li>';
        }
        $html .= '</ul>';

        return $html;
    }
}
