<?php

namespace App\Filament\Resources\OcorrenciaEscolarResource\Pages;

use App\Filament\Resources\OcorrenciaEscolarResource;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\CreateRecord;

class CreateOcorrenciaEscolar extends CreateRecord
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
        ];
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();
        if (! $user || ! $user->can('Create:OcorrenciaEscolar')) {
            return '<p>Você não tem permissão para visualizar o auxílio desta página.</p>';
        }

        return '
            <div class="space-y-3 text-sm">
                <h3 class="font-bold text-base">Nova Ocorrência Escolar</h3>
                <p>Descreva o evento e selecione se a notificação em tempo real deve ser enviada aos pais.</p>
            </div>
        ';
    }
}
