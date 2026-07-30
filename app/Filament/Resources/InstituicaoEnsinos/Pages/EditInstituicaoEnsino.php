<?php

namespace App\Filament\Resources\InstituicaoEnsinos\Pages;

use App\Filament\Resources\InstituicaoEnsinos\InstituicaoEnsinoResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\EditRecord;

class EditInstituicaoEnsino extends EditRecord
{
    protected static string $resource = InstituicaoEnsinoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Editar Instituição de Ensino')
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

        $canUpdate = $user->can('Update:InstituicaoEnsino');
        $canDelete = $user->can('Delete:InstituicaoEnsino');

        $html = '<p>Nesta página você pode alterar os dados da Instituição de Ensino selecionada.</p>';

        if ($canUpdate) {
            $html .= '<h3>Edição de Dados</h3>';
            $html .= '<ul>';
            $html .= '<li>Altere o Código INEP, razão social, CNPJ ou endereço.</li>';
            $html .= '</ul>';
        }

        if ($canDelete) {
            $html .= '<h3>Exclusão</h3>';
            $html .= '<ul>';
            $html .= '<li><strong>Excluir:</strong> Permite remover a instituição caso não haja restrições de integridade.</li>';
            $html .= '</ul>';
        }

        return $html;
    }
}
