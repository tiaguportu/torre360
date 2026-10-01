<?php

namespace App\Filament\Resources\HistoricoEscolars\Pages;

use App\Filament\Resources\HistoricoEscolars\HistoricoEscolarResource;
use App\Models\VideoTutorial;
use App\Services\HistoricoEscolarService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditHistoricoEscolar extends EditRecord
{
    protected static string $resource = HistoricoEscolarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('imprimir_pdf')
                ->label('Emitir PDF Oficial')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->url(fn () => route('historicos-escolares.pdf', $this->record))
                ->openUrlInNewTab(),

            Action::make('sincronizar_dados')
                ->label('Sincronizar Matrículas')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Sincronizar Dados Internos do Torre360')
                ->modalDescription('Deseja reimportar as disciplinas, cargas horárias e médias finais obtidas pelo aluno nas matrículas internas do Torre360?')
                ->modalSubmitActionLabel('Sincronizar Agora')
                ->action(function (HistoricoEscolarService $service) {
                    $resultado = $service->sincronizarMatriculasInternas($this->record);

                    Notification::make()
                        ->success()
                        ->title('Matrículas sincronizadas com sucesso!')
                        ->body("Foram processados {$resultado['anos_sincronizados']} anos letivos e {$resultado['disciplinas_sincronizadas']} disciplinas.")
                        ->send();

                    $this->refreshFormData(['anos']);
                }),

            DeleteAction::make(),

            Action::make('ajuda')
                ->label('Ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->modalHeading('Ajuda: Edição de Histórico Escolar')
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
        $user = auth()->user();
        $canUpdate = $user->can('Update:HistoricoEscolar');

        $html = '<p>Nesta tela você pode revisar e ajustar a matriz curricular e o histórico de estudos do estudante.</p>';
        $html .= '<h4>Orientações:</h4>';
        $html .= '<ul>';
        $html .= '<li><strong>Aba Anos e Séries (Multi-Ano):</strong> Cada item representa uma coluna na matriz oficial. Você pode alterar notas, cargas horárias ou adicionar anos cursados em outras escolas (Origem: <em>Externo</em>).</li>';
        $html .= '<li><strong>Botão "Sincronizar Matrículas":</strong> Atualiza os anos e notas cursados no Torre360 caso novas notas tenham sido lançadas no fechamento do ciclo.</li>';
        $html .= '<li><strong>Botão "Emitir PDF Oficial":</strong> Gera imediatamente o documento formal em formato A4 Paisagem pronto para impressão e assinatura digital.</li>';
        $html .= '</ul>';

        if ($canUpdate) {
            $html .= '<p>✓ Você possui permissão para editar e salvar este documento.</p>';
        }

        return $html;
    }
}
