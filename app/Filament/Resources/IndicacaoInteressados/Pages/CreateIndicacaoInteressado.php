<?php

declare(strict_types=1);

namespace App\Filament\Resources\IndicacaoInteressados\Pages;

use App\Filament\Resources\IndicacaoInteressados\IndicacaoInteressadoResource;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\CreateRecord;

class CreateIndicacaoInteressado extends CreateRecord
{
    protected static string $resource = IndicacaoInteressadoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Nova Indicação')
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
        return '<p>Cadastre uma indicação selecionando a família que indicou e o lead interessado.</p>'
            .'<p>Se o lead for matriculado posteriormente, a indicação será automaticamente marcada como elegível para receber o benefício.</p>';
    }
}
