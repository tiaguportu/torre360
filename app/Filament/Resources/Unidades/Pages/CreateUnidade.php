<?php

namespace App\Filament\Resources\Unidades\Pages;

use App\Filament\Resources\Unidades\UnidadeResource;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\CreateRecord;

class CreateUnidade extends CreateRecord
{
    protected static string $resource = UnidadeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Criar Unidade Escolar')
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

        $canCreate = $user->can('Create:Unidade');

        $html = '<p>Preencha os campos abaixo para cadastrar uma nova Unidade Escolar.</p>';

        if ($canCreate) {
            $html .= '<h3>Instruções de Preenchimento</h3>';
            $html .= '<ul>';
            $html .= '<li><strong>Código INEP:</strong> Código oficial de identificação da escola no INEP.</li>';
            $html .= '<li><strong>Situação de Funcionamento:</strong> Selecione 1-Em atividade, 2-Paralisada ou 3-Extinta.</li>';
            $html .= '<li><strong>Dados Censo/MEC:</strong> Informe a localização (Urbana/Rural), localização diferenciada e dependência administrativa.</li>';
            $html .= '<li><strong>Vínculos com Órgãos Públicos:</strong> Marque os mantenedores ou órgãos vinculados à escola.</li>';
            $html .= '</ul>';
        }

        return $html;
    }
}
