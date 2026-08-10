<?php

namespace App\Filament\Resources\TipoOcorrenciaResource\Pages;

use App\Filament\Resources\TipoOcorrenciaResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListTipoOcorrencias extends ListRecords
{
    protected static string $resource = TipoOcorrenciaResource::class;

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
        if (! $user || ! $user->can('ViewAny:TipoOcorrencia')) {
            return '<p>Você não tem permissão para visualizar o auxílio desta página.</p>';
        }

        return '
            <div class="space-y-3 text-sm">
                <h3 class="font-bold text-base">Tipos de Ocorrências</h3>
                <p>Cadastre as modalidades de ocorrências disciplinares, operacionais ou de elogios da rotina escolar.</p>
            </div>
        ';
    }
}
