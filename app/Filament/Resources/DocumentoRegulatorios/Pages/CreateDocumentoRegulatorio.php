<?php

declare(strict_types=1);

namespace App\Filament\Resources\DocumentoRegulatorios\Pages;

use App\Filament\Resources\DocumentoRegulatorios\DocumentoRegulatorioResource;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\CreateRecord;

class CreateDocumentoRegulatorio extends CreateRecord
{
    protected static string $resource = DocumentoRegulatorioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Cadastrar Documento Regulatório')
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
        $html = '<p>Preencha os dados do documento regulatório, indicando o tipo, número de registro, órgão expedidor e data de validade.</p>';
        $html .= '<p>O sistema calculará automaticamente o status do documento e emitirá avisos quando a validade estiver próxima.</p>';

        return $html;
    }
}
