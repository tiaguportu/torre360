<?php

namespace App\Filament\Resources\PeriodoRematriculas\Pages;

use App\Filament\Resources\PeriodoRematriculas\PeriodoRematriculaResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\EditRecord;

class EditPeriodoRematricula extends EditRecord
{
    protected static string $resource = PeriodoRematriculaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Editar Período de Rematrícula')
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
        $html = '<p>Edite os parâmetros da campanha de rematrícula. Para pausar temporariamente as rematrículas sem alterar as datas, desmarque o campo <em>"Campanha Ativa"</em>.</p>';

        return $html;
    }
}
