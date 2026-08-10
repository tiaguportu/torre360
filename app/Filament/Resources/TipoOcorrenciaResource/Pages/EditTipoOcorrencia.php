<?php

namespace App\Filament\Resources\TipoOcorrenciaResource\Pages;

use App\Filament\Resources\TipoOcorrenciaResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\EditRecord;

class EditTipoOcorrencia extends EditRecord
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

            DeleteAction::make(),
        ];
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();
        if (! $user || ! $user->can('Update:TipoOcorrencia')) {
            return '<p>Você não tem permissão para visualizar o auxílio desta página.</p>';
        }

        return '
            <div class="space-y-3 text-sm">
                <h3 class="font-bold text-base">Editar Tipo de Ocorrência</h3>
                <p>Atualize a classificação e configurações deste tipo de ocorrência.</p>
            </div>
        ';
    }
}
