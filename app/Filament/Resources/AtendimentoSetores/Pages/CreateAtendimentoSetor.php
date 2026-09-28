<?php

namespace App\Filament\Resources\AtendimentoSetores\Pages;

use App\Filament\Resources\AtendimentoSetores\AtendimentoSetorResource;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\CreateRecord;

class CreateAtendimentoSetor extends CreateRecord
{
    protected static string $resource = AtendimentoSetorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getHelpHeaderAction(),
        ];
    }

    protected function getHelpHeaderAction(): Action
    {
        return Action::make('ajuda')
            ->label('Ajuda')
            ->icon('heroicon-o-question-mark-circle')
            ->color('gray')
            ->modalHeading('Ajuda: Criar Setor')
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
        return '<p class="text-sm text-gray-600 dark:text-gray-300">Cadastre um novo setor de atendimento institucional.</p>';
    }
}
