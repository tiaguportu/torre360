<?php

namespace App\Filament\Resources\AtendimentoSetores\Pages;

use App\Filament\Resources\AtendimentoSetores\AtendimentoSetorResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\ListRecords;

class ListAtendimentoSetores extends ListRecords
{
    protected static string $resource = AtendimentoSetorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->getHelpHeaderAction(),
        ];
    }

    protected function getHelpHeaderAction(): Action
    {
        return Action::make('ajuda')
            ->label('Ajuda')
            ->icon('heroicon-o-question-mark-circle')
            ->color('gray')
            ->modalHeading('Ajuda: Setores de Atendimento')
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
        $html = '<div class="space-y-4">';
        $html .= '<p class="text-sm text-gray-600 dark:text-gray-300">Cadastre e organize os <strong>Setores de Atendimento</strong> disponíveis para os responsáveis escolherem no Portal da Família (ex: Secretaria, Financeiro, Coordenação).</p>';
        $html .= '</div>';

        return $html;
    }
}
