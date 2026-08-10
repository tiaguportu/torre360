<?php

namespace App\Filament\Resources\TipoOcorrenciaResource\Pages;

use App\Filament\Resources\TipoOcorrenciaResource;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\CreateRecord;

class CreateTipoOcorrencia extends CreateRecord
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
        ];
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();
        if (! $user || ! $user->can('Create:TipoOcorrencia')) {
            return '<p>Você não tem permissão para visualizar o auxílio desta página.</p>';
        }

        return '
            <div class="space-y-3 text-sm">
                <h3 class="font-bold text-base">Novo Tipo de Ocorrência</h3>
                <p>Configure o nome, a gravidade e o comportamento padrão de notificação aos pais.</p>
            </div>
        ';
    }
}
