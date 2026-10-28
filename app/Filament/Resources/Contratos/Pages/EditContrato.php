<?php

namespace App\Filament\Resources\Contratos\Pages;

use App\Filament\Resources\Contratos\Actions\AcoesContrato;
use App\Filament\Resources\Contratos\ContratoResource;
use App\Models\Contrato;
use App\Services\GeracaoFaturasContratoService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
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
                ->label(fn (?Contrato $record) => $record && $record->faturas()->exists() ? 'Regenerar Faturas' : 'Gerar Faturas Automaticamente')
                ->icon(fn (?Contrato $record) => $record && $record->faturas()->exists() ? 'heroicon-o-arrow-path' : 'heroicon-o-banknotes')
                ->color(fn (?Contrato $record) => $record && $record->faturas()->exists() ? 'warning' : 'success')
                ->requiresConfirmation(false)
                ->visible(fn (?Contrato $record) => $record !== null)
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
                    DatePicker::make('data_base')
                        ->label('Data-base dos vencimentos')
                        ->helperText('A entrada (se houver) vence nesta data; a 1ª parcela, 5 dias úteis depois; as demais, mês a mês. Sugerida: a data de aceite do contrato ou, se ele ainda não foi assinado, hoje.')
                        ->default(fn ($livewire): string => ($livewire->getRecord()->data_aceite ?? today())->toDateString())
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->required(),
                ])
                ->modalHeading(fn (?Contrato $record) => $record && $record->faturas()->exists() ? 'Regenerar Faturas do Contrato' : 'Gerar Faturas Automaticamente')
                ->modalDescription(fn (?Contrato $record) => $record && $record->faturas()->exists()
                    ? 'Atenção: As faturas existentes serão substituídas pelas novas parcelas calculadas com base no valor atual do contrato. Faturas com pagamento registrado ou cobrança emitida no gateway impedem a regeneração. Caso já tenha sido enviado para o Assinafy, os registros anteriores de assinatura serão resetados.'
                    : 'As faturas podem ser geradas antes da assinatura: elas passam a constar no contrato enviado para assinatura. Os vencimentos partem da data-base informada abaixo.')
                ->modalSubmitActionLabel(fn (?Contrato $record) => $record && $record->faturas()->exists() ? 'Sim, Regenerar Faturas' : 'Gerar Faturas')
                ->action(function (array $data, EditRecord $livewire): void {
                    $contrato = $livewire->getRecord();
                    $qtdParcelas = (int) $data['quantidade_parcelas'];
                    $valorEntrada = (float) $data['valor_entrada'];
                    $dataBase = filled($data['data_base'] ?? null) ? Carbon::parse($data['data_base']) : null;

                    try {
                        $faturas = app(GeracaoFaturasContratoService::class)->gerar($contrato, $qtdParcelas, $valorEntrada, $dataBase);
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

                    activity('contrato')
                        ->performedOn($contrato)
                        ->causedBy(auth()->user())
                        ->withProperties([
                            'tipo' => 'geracao_faturas',
                            'quantidade_parcelas' => $qtdParcelas,
                            'valor_entrada' => $valorEntrada,
                            'valor_total_contrato' => (float) $contrato->valor_total,
                        ])
                        ->log("Faturas do Contrato #{$contrato->id} foram processadas pelo operador.");

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

            AcoesContrato::sincronizarAssinafy(),
            AcoesContrato::downloadContrato(),

            DeleteAction::make(),

            Action::make('ajuda')
                ->label('Ajuda')
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
            $action->requiresConfirmation(false);
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

    protected function afterSave(): void
    {
        /** @var Contrato $contrato */
        $contrato = $this->getRecord();

        if ($contrato->faturas()->exists()) {
            $totalFaturas = $contrato->totalFaturasEmitidas();
            $valorContrato = (float) $contrato->valor_total;

            if (abs($valorContrato - $totalFaturas) > 0.01) {
                Notification::make()
                    ->title('Divergência entre Contrato e Faturas')
                    ->body(sprintf(
                        'O valor contratual (R$ %s) divergiu do saldo das faturas já emitidas (R$ %s). Recomendamos utilizar a ação "Regenerar Faturas" no topo da página para sincronizar as cobranças.',
                        number_format($valorContrato, 2, ',', '.'),
                        number_format($totalFaturas, 2, ',', '.')
                    ))
                    ->warning()
                    ->persistent()
                    ->send();
            }
        }
    }

    private function getHelpContent(): string
    {
        return view('filament.resources.contratos.help-edit', [
            'user' => auth()->user(),
        ])->render();
    }
}
