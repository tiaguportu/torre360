<?php

namespace App\Filament\Resources\PeriodoLetivos\Pages;

use App\Filament\Resources\PeriodoLetivos\PeriodoLetivoResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListPeriodoLetivos extends ListRecords
{
    protected static string $resource = PeriodoLetivoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Períodos Letivos')
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

        $canCreate = $user->can('Create:PeriodoLetivo');
        $canUpdate = $user->can('Update:PeriodoLetivo');

        $html = '<p>Nesta página você gerencia os anos/semestres letivos da instituição.</p>';
        $html .= '<h3>O que você pode fazer?</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>Calendário:</strong> Visualize as datas de início e fim de cada período letivo.</li>';

        if ($canCreate) {
            $html .= '<li><strong>Novo Período:</strong> Crie um novo ano letivo definindo as datas vigentes.</li>';
        }

        if ($canUpdate) {
            $html .= '<li><strong>Editar:</strong> Ajuste prazos ou encerre períodos passados.</li>';
        }

        $html .= '<li><strong>Vigência:</strong> O período letivo controla a disponibilidade de turmas e matrículas no sistema.</li>';
        $html .= '</ul>';

        return $html;
    }
}
