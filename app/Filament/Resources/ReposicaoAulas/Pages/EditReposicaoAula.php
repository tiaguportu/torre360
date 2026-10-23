<?php

namespace App\Filament\Resources\ReposicaoAulas\Pages;

use App\Filament\Resources\ReposicaoAulas\ReposicaoAulaResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\EditRecord;

class EditReposicaoAula extends EditRecord
{
    protected static string $resource = ReposicaoAulaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Editar Reposição')
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
        return '<p>Edite os dados da reposição agendada ou atualize seu status para Realizada ou Cancelada.</p>';
    }
}
