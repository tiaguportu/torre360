<?php

namespace App\Filament\Resources\AtendimentoSetores\Pages;

use App\Filament\Resources\AtendimentoSetores\AtendimentoSetorResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\EditRecord;

class EditAtendimentoSetor extends EditRecord
{
    protected static string $resource = AtendimentoSetorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            $this->getHelpHeaderAction(),
        ];
    }

    protected function getHelpHeaderAction(): Action
    {
        return Action::make('ajuda')
            ->label('Ajuda')
            ->icon('heroicon-o-question-mark-circle')
            ->color('gray')
            ->modalHeading('Ajuda: Editar Setor')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->form([
                ViewField::make('help_content')
                    ->view('filament.components.help-content')
                    ->viewData(['content' => $this->getHelpContent()]),
            ]);
    }

    private function getHelpContent(): string
    {
        return '<p class="text-sm text-gray-600 dark:text-gray-300">Atualize as informações do setor de atendimento.</p>';
    }
}
