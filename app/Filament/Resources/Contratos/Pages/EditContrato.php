<?php

namespace App\Filament\Resources\Contratos\Pages;

use App\Filament\Resources\Contratos\ContratoResource;
use App\Models\Contrato;
use App\Services\GeracaoFaturasContratoService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditContrato extends EditRecord
{
    protected static string $resource = ContratoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('gerarFaturas')
                ->label('Gerar Faturas Automaticamente')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->requiresConfirmation(false)
                ->visible(fn ($record) => $record && ! $record->faturas()->exists())
                ->schema([
                    TextInput::make('quantidade_parcelas')
                        ->label('Quantidade de Parcelas')
                        ->helperText('Número de parcelas em que o valor restante (após entrada) será dividido.')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->required(),
                    TextInput::make('valor_entrada')
                        ->label('Valor de Entrada (R$)')
                        ->helperText('Informe 0 caso não haja entrada. O valor por parcela será: (Total − Entrada) ÷ Parcelas.')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('R$')
                        ->required(),
                ])
                ->modalHeading('Gerar Faturas Automaticamente')
                ->modalDescription('A 1ª parcela vencerá em 5 dias úteis a partir da data de aceite. As demais serão mensais a partir daí.')
                ->modalSubmitActionLabel('Gerar Faturas')
                ->action(function (array $data, EditRecord $livewire): void {
                    $contrato = $livewire->getRecord();
                    $qtdParcelas = (int) $data['quantidade_parcelas'];
                    $valorEntrada = (float) $data['valor_entrada'];

                    try {
                        $faturas = app(GeracaoFaturasContratoService::class)->gerar($contrato, $qtdParcelas, $valorEntrada);
                    } catch (\InvalidArgumentException $e) {
                        Notification::make()
                            ->title('Erro')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    $primeiraParcela = $faturas->skip($valorEntrada > 0 ? 1 : 0)->first();

                    Notification::make()
                        ->title('Faturas geradas com sucesso!')
                        ->body(
                            ($valorEntrada > 0 ? '1 fatura de entrada + ' : '').
                                $qtdParcelas.' parcela(s) criada(s).'.
                                ($primeiraParcela ? ' 1ª parcela: '.Carbon::parse($primeiraParcela->vencimento)->format('d/m/Y').'.' : '')
                        )
                        ->success()
                        ->send();

                    if ($contrato->jaEnviadoAssinafy()) {
                        $contrato->resetAssinafyState();
                        Notification::make()
                            ->title('Assinatura Resetada')
                            ->body('Como as faturas foram regeradas, os registros anteriores de assinatura no Assinafy foram limpos.')
                            ->warning()
                            ->send();
                    }
                }),

            Action::make('visualizarContrato')
                ->label('Visualizar Contrato')
                ->icon('heroicon-o-document-magnifying-glass')
                ->color('info')
                ->url(fn ($record) => route('contratos.visualizar', $record))
                ->openUrlInNewTab(),

            DeleteAction::make(),

            Action::make('ajuda')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->form([
                    ViewField::make('help')
                        ->view('filament.components.help-content')
                        ->viewData([
                            'content' => $this->getHelpContent(),
                        ]),
                ])
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fechar'),
        ];
    }

    protected function getSaveFormAction(): Action
    {
        $action = parent::getSaveFormAction();

        if ($this->getRecord()?->jaEnviadoAssinafy()) {
            $action
                ->requiresConfirmation()
                ->modalHeading('Confirmar Alteração de Contrato Enviado')
                ->modalDescription('Atenção: Este contrato já foi enviado para a plataforma Assinafy. Ao salvar as alterações, as informações e assinaturas anteriores serão resetadas, sendo necessário submeter o contrato novamente para assinatura digital. Deseja continuar?')
                ->modalSubmitActionLabel('Sim, alterar e resetar assinatura');
        } else {
            $action
                ->requiresConfirmation()
                ->modalHeading('Confirmar Salvar Alterações')
                ->modalDescription('Deseja realmente salvar as alterações efetuadas neste contrato?')
                ->modalSubmitActionLabel('Sim, salvar');
        }

        return $action;
    }

    protected function beforeSave(): void
    {
        /** @var Contrato $contrato */
        $contrato = $this->getRecord();

        if ($contrato->jaEnviadoAssinafy()) {
            $contrato->resetAssinafyState();

            Notification::make()
                ->title('Assinatura do Assinafy Resetada')
                ->body('Como o contrato foi modificado, as informações anteriores de assinatura foram limpas. Um novo envio poderá ser realizado ao visualizar o contrato.')
                ->warning()
                ->send();
        }
    }

    private function getHelpContent(): string
    {
        $user = auth()->user();
        $html = '<div class="space-y-4">';

        $html .= '<p>Esta página permite editar as informações de um contrato de matrícula, gerenciar o faturamento e iniciar o processo de assinatura digital.</p>';

        $html .= '<h3 class="text-lg font-bold mt-4">⚙️ Funcionalidades Disponíveis</h3>';
        $html .= '<ul class="list-disc ml-6 space-y-1">';
        $html .= '<li><strong>Gerar Faturas Automaticamente:</strong> Permite criar o parcelamento do contrato (entrada + parcelas). Esta ação <em>só fica visível se o contrato não possuir nenhuma fatura gerada anteriormente</em>.</li>';
        $html .= '<li><strong>Visualizar Contrato:</strong> Abre a página de visualização do contrato, onde é possível assinar digitalmente via integração com a Assinafy ou baixar o documento PDF.</li>';
        $html .= '</ul>';

        $html .= '<hr class="my-4">';
        $html .= '<h3 class="text-lg font-bold">🛡️ Permissões e Acesso</h3>';
        $html .= '<ul class="list-disc ml-6 space-y-1">';

        if ($user->can('Update:Contrato')) {
            $html .= '<li>✅ Você tem permissão para editar os dados básicos do contrato.</li>';
        }
        if ($user->can('Delete:Contrato')) {
            $html .= '<li>✅ Você tem permissão para excluir este contrato do sistema.</li>';
        }

        $html .= '</ul>';
        $html .= '</div>';

        return $html;
    }
}
