<?php

namespace App\Filament\Resources\Pessoas\Pages;

use App\Filament\Concerns\HasAjudaAction;
use App\Filament\Resources\Pessoas\PessoaResource;
use App\Support\HelpContent;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPessoa extends EditRecord
{
    use HasAjudaAction;

    protected static string $resource = PessoaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            $this->ajudaAction('Dados Cadastrais', $this->getHelpContent()),
        ];
    }

    private function getHelpContent(): HelpContent
    {
        $user = auth()->user();

        return HelpContent::make('🪪', 'Dados Cadastrais', 'Ficha de cadastro da pessoa (aluno, responsável, professor ou funcionário).')
            ->secao('🎯 O que você pode fazer?', [
                ['👀', 'Conferir os dados', 'Veja as informações cadastradas e confirme se estão corretas.'],
                $user->can('Update:Pessoa') ? ['✏️', 'Atualizar', 'Corrija os campos necessários e salve ao final da página.'] : null,
                $user->can('Delete:Pessoa') ? ['🗑️', 'Excluir', 'Remove o cadastro; use apenas quando tiver certeza.'] : null,
            ])
            ->dica('Dados desatualizados, como telefone e e-mail, atrapalham os avisos da escola. Se algo estiver errado e você não puder alterar, fale com a secretaria.');
    }
}
