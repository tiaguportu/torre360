<?php

namespace App\Filament\Resources\Unidades\Pages;

use App\Filament\Resources\Unidades\UnidadeResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListUnidades extends ListRecords
{
    protected static string $resource = UnidadeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Gestão de Unidades Escolares')
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
        $canUpdate = $user->can('Update:Unidade');

        $html = '<p>Nesta página você pode gerenciar as Unidades Escolares da instituição.</p>';
        $html .= '<h3>O que você pode fazer?</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Visualização:</strong> Consulte o código INEP, a situação de funcionamento (Em atividade, Paralisada ou Extinta) e demais dados da unidade.</li>';

        if ($canCreate) {
            $html .= '<li><strong>Nova Unidade:</strong> Cadastre uma nova unidade informando os dados do Censo/INEP, localização, dependência administrativa e mantenedores.</li>';
        }

        if ($canUpdate) {
            $html .= '<li><strong>Editar:</strong> Altere os dados de contato, situação de funcionamento e parâmetros do INEP da unidade.</li>';
        }

        $html .= '</ul>';

        return $html;
    }
}
