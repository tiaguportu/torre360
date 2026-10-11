<?php

namespace App\Filament\Resources\RetencaoTarefas\Pages;

use App\Filament\Resources\RetencaoTarefas\RetencaoTarefaResource;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Pages\EditRecord;

class EditRetencaoTarefa extends EditRecord
{
    protected static string $resource = RetencaoTarefaResource::class;

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
        if (! $user || ! $user->can('Update:RetencaoTarefa')) {
            return '<p>Você não tem permissão para editar esta tarefa de retenção.</p>';
        }

        return '
            <div class="space-y-3 text-sm">
                <h3 class="font-bold text-base">Edição do Acompanhamento de Retenção</h3>
                <p>Nesta tela você pode consultar o diagnóstico completo dos fatores de evasão (frequência, notas, inadimplência e status da rematrícula) e registrar as medidas tomadas pela escola.</p>
                <ul class="list-disc pl-5 space-y-1">
                    <li><strong>Status:</strong> Altere para "Em Andamento" enquanto atua junto à família ou "Concluído" ao finalizar.</li>
                    <li><strong>Parecer Final:</strong> Documente os acordos pedagógicos e condições financeiras combinadas.</li>
                </ul>
            </div>
        ';
    }
}
