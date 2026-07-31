<?php

namespace App\Filament\Resources\Pais\Pages;

use App\Filament\Resources\Pais\PaisResource;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\CreateRecord;

class CreatePais extends CreateRecord
{
    protected static string $resource = PaisResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Cadastrar País')
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
        return '<p>Preencha os campos para cadastrar um novo país. Certifique-se de informar o código INEP oficial para o Censo Escolar.</p>';
    }
}
