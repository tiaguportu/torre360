<?php

namespace App\Filament\Resources\ApuracaoHoraAulas\Pages;

use App\Filament\Resources\ApuracaoHoraAulas\ApuracaoHoraAulaResource;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\CreateRecord;

class CreateApuracaoHoraAula extends CreateRecord
{
    protected static string $resource = ApuracaoHoraAulaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['criado_por_user_id'] = auth()->id();

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Cadastrar Apuração')
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
        return '<p>Cadastre manualmente uma apuração de horas-aulas para um professor na competência desejada.</p>';
    }
}
