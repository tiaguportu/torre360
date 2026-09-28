<?php

namespace App\Filament\Resources\PeriodoRematriculas\Pages;

use App\Filament\Resources\PeriodoRematriculas\PeriodoRematriculaResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListPeriodoRematriculas extends ListRecords
{
    protected static string $resource = PeriodoRematriculaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Abrir Período de Rematrícula'),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Campanhas de Rematrícula Online')
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
        $html = '<p>Nesta tela a gestão escolar configura e ativa as <strong>Campanhas de Rematrícula Online</strong> para o Portal da Família.</p>';
        $html .= '<h4>Como Funciona a Rematrícula Online:</h4>';
        $html .= '<ul>';
        $html .= '<li><strong>Período de Origem:</strong> Selecione o período em que os alunos estão estudando hoje (ex: 2026).</li>';
        $html .= '<li><strong>Período de Destino:</strong> Selecione o ano letivo para onde eles irão no próximo ano (ex: 2027).</li>';
        $html .= '<li><strong>Portal da Família:</strong> Quando a campanha estiver ativa e no prazo de vigência, os responsáveis acessam <em>"Rematrícula Online"</em> no Portal, confirmam os dados do estudante, escolhem a turma/turno e concluem o aceite.</li>';
        $html .= '<li><strong>Automação:</strong> O sistema pode gerar o contrato de prestação de serviços do próximo ano automaticamente.</li>';
        $html .= '</ul>';

        return $html;
    }
}
