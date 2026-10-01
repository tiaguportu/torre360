<?php

namespace App\Filament\Resources\HistoricoEscolars\Pages;

use App\Filament\Resources\HistoricoEscolars\HistoricoEscolarResource;
use App\Models\VideoTutorial;
use App\Services\HistoricoEscolarService;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateHistoricoEscolar extends CreateRecord
{
    protected static string $resource = HistoricoEscolarResource::class;

    protected function afterCreate(): void
    {
        // Se o aluno possui matrículas no Torre360 e não foram adicionados anos manualmente, sincroniza automaticamente
        if ($this->record->anos()->doesntExist()) {
            $service = app(HistoricoEscolarService::class);
            $res = $service->sincronizarMatriculasInternas($this->record);

            if ($res['anos_sincronizados'] > 0) {
                Notification::make()
                    ->success()
                    ->title('Matrículas internas sincronizadas!')
                    ->body("Foram importados automaticamente {$res['anos_sincronizados']} anos letivos e {$res['disciplinas_sincronizadas']} componentes curriculares das matrículas deste aluno.")
                    ->send();
            }
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Criar Histórico Escolar')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar')
                ->form([
                    ViewField::make('help_content')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                            'video' => VideoTutorial::query()->ativo()->where('chave_pagina', 'historico-escolar-multi-ano')->first(),
                        ]),
                ]),
        ];
    }

    private function getHelpContent(): string
    {
        $html = '<p>Preencha os dados básicos do estudante para lavrar um novo <strong>Histórico Escolar Oficial</strong>.</p>';
        $html .= '<ol>';
        $html .= '<li><strong>Estudante:</strong> Selecione o aluno matriculado.</li>';
        $html .= '<li><strong>Etapa / Curso:</strong> Escolha se este histórico é referente ao Ensino Fundamental ou Ensino Médio.</li>';
        $html .= '<li><strong>Situação:</strong> Defina se o ciclo foi concluído, se o aluno ainda está em curso ou se é uma transferência.</li>';
        $html .= '<li><strong>Sincronização Automática:</strong> Ao salvar, o sistema importará automaticamente as notas, disciplinas e cargas horárias das matrículas cursadas no Torre360.</li>';
        $html .= '</ol>';

        return $html;
    }
}
