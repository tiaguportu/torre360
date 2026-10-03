<?php

declare(strict_types=1);

namespace App\Filament\Resources\IndicacaoInteressados\Pages;

use App\Filament\Resources\IndicacaoInteressados\IndicacaoInteressadoResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\EditRecord;

class EditIndicacaoInteressado extends EditRecord
{
    protected static string $resource = IndicacaoInteressadoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Editar Indicação')
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
        return '<p>Edite os dados da indicação, defina o tipo de recompensa ou ajuste a situação cadastral.</p>';
    }
}
