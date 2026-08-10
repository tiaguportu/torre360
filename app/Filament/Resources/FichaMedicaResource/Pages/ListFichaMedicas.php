<?php

namespace App\Filament\Resources\FichaMedicaResource\Pages;

use App\Filament\Resources\FichaMedicaResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListFichaMedicas extends ListRecords
{
    protected static string $resource = FichaMedicaResource::class;

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

            CreateAction::make(),
        ];
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();
        if (! $user || ! $user->can('ViewAny:FichaMedica')) {
            return '<p>Você não tem permissão para visualizar o auxílio desta página.</p>';
        }

        return '
            <div class="space-y-3 text-sm">
                <h3 class="font-bold text-base">Fichas Médicas e Restrições Alimentares</h3>
                <p>Gerencie o prontuário de saúde dos estudantes:</p>
                <ul class="list-disc pl-5 space-y-1">
                    <td><b>Restrições Alimentares:</b> Alertas de lactose, glúten, amendoim e outras alergias para a cantina e refeitório.</td>
                    <td><b>Medicamentos de Uso Contínuo:</b> Registro de dosagens e horários autorizados pelos responsáveis.</td>
                    <td><b>Contatos de Emergência:</b> Telefones e contatos para casos de urgência médica.</td>
                </ul>
            </div>
        ';
    }
}
