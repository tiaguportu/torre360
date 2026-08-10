<?php

namespace App\Filament\Resources\OcorrenciaEscolarResource\Pages;

use App\Filament\Resources\OcorrenciaEscolarResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListOcorrenciaEscolars extends ListRecords
{
    protected static string $resource = OcorrenciaEscolarResource::class;

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
        if (! $user || ! $user->can('ViewAny:OcorrenciaEscolar')) {
            return '<p>Você não tem permissão para visualizar o auxílio desta página.</p>';
        }

        return '
            <div class="space-y-3 text-sm">
                <h3 class="font-bold text-base">Ocorrências da Rotina Escolar</h3>
                <p>Gerencie o registro de ocorrências disciplinares, operacionais e pedagógicas:</p>
                <ul class="list-disc pl-5 space-y-1">
                    <td><b>Atrasos e Uniforme:</b> Registro da rotina com notificação aos responsáveis.</td>
                    <td><b>Advertências e Elogios:</b> Rastreamento de atitudes com alertas em tempo real.</td>
                    <td><b>Notificação Desativável:</b> Permite desativar a notificação para uma ocorrência específica.</td>
                </ul>
            </div>
        ';
    }
}
