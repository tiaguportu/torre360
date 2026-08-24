<?php

namespace App\Filament\Resources\Interessados\Pages;

use App\Filament\Resources\Interessados\Actions\ImportarLeadIaAction;
use App\Filament\Resources\Interessados\InteressadoResource;
use App\Services\LeadScoreService;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\CreateRecord;

class CreateInteressado extends CreateRecord
{
    protected static string $resource = InteressadoResource::class;

    protected function afterCreate(): void
    {
        LeadScoreService::recalcular($this->record);
    }

    protected function getHeaderActions(): array
    {
        return [
            ImportarLeadIaAction::make(),
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Novo Interessado')
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
        $html = '<p>Nesta página você pode cadastrar um novo interessado (lead) no sistema CRM.</p>';
        $html .= '<h3>Passo a Passo:</h3>';
        $html .= '<ol>';
        $html .= '<li><strong>Pessoa:</strong> Selecione ou crie a pessoa interessada. Se a pessoa já existir no sistema, seus dados serão preenchidos automaticamente.</li>';
        $html .= '<li><strong>Status:</strong> Defina o status inicial do lead (normalmente "Novo").</li>';
        $html .= '<li><strong>Origem:</strong> Informe como o interessado chegou até a escola.</li>';
        $html .= '<li><strong>Consultor:</strong> Atribua um consultor responsável pelo atendimento.</li>';
        $html .= '<li><strong>Dependentes:</strong> Na aba "Dependentes", adicione os alunos que serão matriculados.</li>';
        $html .= '</ol>';
        $html .= '<p><strong>Dica:</strong> Agende o próximo contato para não perder o timing de follow-up!</p>';

        return $html;
    }
}
