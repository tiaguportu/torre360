<?php

namespace App\Filament\Resources\InstituicaoEnsinos\Pages;

use App\Filament\Resources\InstituicaoEnsinos\InstituicaoEnsinoResource;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\CreateRecord;

class CreateInstituicaoEnsino extends CreateRecord
{
    protected static string $resource = InstituicaoEnsinoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Criar Instituição de Ensino')
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

        $html = '<p>Preencha os dados abaixo para cadastrar uma nova Instituição de Ensino.</p>';

        if ($canCreate) {
            $html .= '<h3>Instruções de Cadastro</h3>';
            $html .= '<ul>';
            $html .= '<li><strong>Código INEP:</strong> Informe o código identificador oficial junto ao INEP/MEC.</li>';
            $html .= '<li><strong>Dados Principais:</strong> Nome oficial, CNPJ e logo da instituição.</li>';
            $html .= '<li><strong>Endereço e Redes:</strong> Vincule o endereço principal e os canais de comunicação.</li>';
            $html .= '</ul>';
        }

        return $html;
    }
}
