<?php

namespace App\Filament\Resources\InstituicaoEnsinos\Pages;

use App\Filament\Resources\InstituicaoEnsinos\InstituicaoEnsinoResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListInstituicaoEnsinos extends ListRecords
{
    protected static string $resource = InstituicaoEnsinoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Gestão de Instituições de Ensino')
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

        $canCreate = $user->can('Create:InstituicaoEnsino');
        $canUpdate = $user->can('Update:InstituicaoEnsino');

        $html = '<p>Nesta página você pode gerenciar as Instituições de Ensino mantenedoras da rede escolar.</p>';
        $html .= '<h3>O que você pode fazer?</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Visualização:</strong> Consulte o código INEP, CNPJ e nome das instituições cadastradas.</li>';

        if ($canCreate) {
            $html .= '<li><strong>Cadastrar Instituição:</strong> Adicione uma nova Instituição de Ensino com seu respetivo Código INEP.</li>';
        }

        if ($canUpdate) {
            $html .= '<li><strong>Editar:</strong> Atualize os dados cadastrais, endereço e mídias sociais da instituição.</li>';
        }

        $html .= '</ul>';

        return $html;
    }
}
