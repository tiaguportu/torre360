<?php

namespace App\Filament\Resources\AtendimentoEnfermagemResource\Pages;

use App\Filament\Resources\AtendimentoEnfermagemResource;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\CreateRecord;

class CreateAtendimentoEnfermagem extends CreateRecord
{
    protected static string $resource = AtendimentoEnfermagemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData(['content' => $this->getHelpContent()]),
                ])
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar'),
        ];
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();
        if (! $user || ! $user->can('Create:AtendimentoEnfermagem')) {
            return '<p>Você não tem permissão para visualizar o auxílio desta página.</p>';
        }

        return '
            <div class="space-y-3 text-sm">
                <h3 class="font-bold text-base">Novo Atendimento no Ambulatório</h3>
                <p>Registre os sintomas, medicação administrada e conduta adotada.</p>
            </div>
        ';
    }
}
