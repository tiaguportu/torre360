<?php

declare(strict_types=1);

namespace App\Filament\Resources\DocumentoRegulatorios\Pages;

use App\Filament\Resources\DocumentoRegulatorios\DocumentoRegulatorioResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\EditRecord;

class EditDocumentoRegulatorio extends EditRecord
{
    protected static string $resource = DocumentoRegulatorioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Editar Documento Regulatório')
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
        $html = '<p>Atualize as datas de renovação, número do protocolo ou anexe a nova via digitalizada do documento.</p>';

        return $html;
    }
}
