<?php

namespace App\Filament\Resources\ApuracaoHoraAulas\Pages;

use App\Filament\Resources\ApuracaoHoraAulas\ApuracaoHoraAulaResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\EditRecord;

class EditApuracaoHoraAula extends EditRecord
{
    protected static string $resource = ApuracaoHoraAulaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Editar Apuração')
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
        return '<p>Edite os valores apurados, adicione horas-atividade ou atualize o status de conferência da competência.</p>';
    }
}
