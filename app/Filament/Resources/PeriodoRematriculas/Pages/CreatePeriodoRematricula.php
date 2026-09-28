<?php

namespace App\Filament\Resources\PeriodoRematriculas\Pages;

use App\Filament\Resources\PeriodoRematriculas\PeriodoRematriculaResource;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\CreateRecord;

class CreatePeriodoRematricula extends CreateRecord
{
    protected static string $resource = PeriodoRematriculaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Novo Período de Rematrícula')
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
        $html = '<p>Configure as datas de vigência, os períodos de origem e destino e a mensagem de boas-vindas que será exibida para os pais na rematrícula online.</p>';

        return $html;
    }
}
