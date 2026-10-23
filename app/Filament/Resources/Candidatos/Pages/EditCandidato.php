<?php

declare(strict_types=1);

namespace App\Filament\Resources\Candidatos\Pages;

use App\Filament\Resources\Candidatos\CandidatoResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\EditRecord;

class EditCandidato extends EditRecord
{
    protected static string $resource = CandidatoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
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
        if (! $user || ! $user->can('Update:Candidato')) {
            return '<p>Você não tem permissão para visualizar o auxílio desta página.</p>';
        }

        return '
            <div class="space-y-4 text-sm">
                <h3 class="font-bold text-base text-gray-900 dark:text-gray-100">Edição de Candidato / Banco de Talentos</h3>
                <p class="text-gray-600 dark:text-gray-400">Revise e atualize os dados do candidato, histórico de entrevistas, foto e parecer de RH.</p>

                <div class="space-y-2">
                    <h4 class="font-semibold text-gray-800 dark:text-gray-200">Recursos disponíveis:</h4>
                    <ul class="list-disc pl-5 space-y-1 text-gray-600 dark:text-gray-400">
                        <li><strong>Reprocessamento de Currículo:</strong> Se enviar uma nova versão do currículo, você pode reacionar o botão de IA para extrair informações atualizadas.</li>
                        <li><strong>Análise de Áudio:</strong> Carregue um áudio de WhatsApp para consolidar as impressões da entrevista e complementar dados do formulário.</li>
                        <li><strong>Status da Candidatura:</strong> Altere a situação do candidato entre Novo, Em Triagem, Entrevistado, Banco de Talentos, Aprovado ou Reprovado.</li>
                    </ul>
                </div>
            </div>
        ';
    }
}
