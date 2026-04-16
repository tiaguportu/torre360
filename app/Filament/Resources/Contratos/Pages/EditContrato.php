<?php

namespace App\Filament\Resources\Contratos\Pages;

use App\Filament\Resources\Contratos\ContratoResource;
use App\Models\Fatura;
use App\Models\ItemFatura;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditContrato extends EditRecord
{
    protected static string $resource = ContratoResource::class;

    /**
     * Avança N dias úteis a partir de uma data, pulando sábados e domingos.
     */
    private function adicionarDiasUteis(Carbon $data, int $dias): Carbon
    {
        $resultado = $data->copy();
        $adicionados = 0;

        while ($adicionados < $dias) {
            $resultado->addDay();

            if (! $resultado->isWeekend()) {
                $adicionados++;
            }
        }

        return $resultado;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('gerarFaturas')
                ->label('Gerar Faturas Automaticamente')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->requiresConfirmation(false)
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

                    if (! $contrato->data_aceite) {
                        Notification::make()
                            ->title('Erro')
                            ->body('O contrato não possui data de aceite definida.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $qtdParcelas = (int) $data['quantidade_parcelas'];
                    $valorEntrada = (float) $data['valor_entrada'];
                    $valorTotal = (float) $contrato->valor_total;
                    $valorRestante = $valorTotal - $valorEntrada;

                    if ($valorRestante < 0) {
                        Notification::make()
                            ->title('Erro')
                            ->body('O valor de entrada não pode ser maior que o valor total do contrato.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $valorParcela = $qtdParcelas > 0 ? round($valorRestante / $qtdParcelas, 2) : 0;

                    // 1ª parcela: 5 dias úteis a partir do data_aceite (pula fins de semana)
                    $dataAceite = Carbon::parse($contrato->data_aceite);
                    $primeiroVencimento = $this->adicionarDiasUteis($dataAceite, 5);

                    DB::transaction(function () use ($contrato, $valorEntrada, $valorParcela, $qtdParcelas, $dataAceite, $primeiroVencimento): void {
                        // Remove faturas existentes e seus itens
                        $contrato->faturas()->each(function (Fatura $fatura): void {
                            $fatura->itens()->delete();
                            $fatura->delete();
                        });

                        // Fatura de entrada: vencimento no próprio data_aceite
                        if ($valorEntrada > 0) {
                            /** @var Fatura $faturaEntrada */
                            $faturaEntrada = Fatura::create([
                                'contrato_id' => $contrato->id,
                                'vencimento' => $dataAceite->toDateString(),
                                'status' => 'pendente',
                            ]);

                            ItemFatura::create([
                                'fatura_id' => $faturaEntrada->id,
                                'descricao' => 'Entrada',
                                'valor_unitario' => $valorEntrada,
                                'quantidade' => 1,
                                'desconto' => 0,
                                'tipo_desconto' => 'absoluto',
                            ]);
                        }

                        // Parcelas: 1ª = 5 dias úteis após aceite, demais = +1 mês cada
                        for ($i = 0; $i < $qtdParcelas; $i++) {
                            $vencimento = $primeiroVencimento->copy()->addMonths($i);

                            /** @var Fatura $fatura */
                            $fatura = Fatura::create([
                                'contrato_id' => $contrato->id,
                                'vencimento' => $vencimento->toDateString(),
                                'status' => 'pendente',
                            ]);

                            ItemFatura::create([
                                'fatura_id' => $fatura->id,
                                'descricao' => 'Parcela '.($i + 1).' de '.$qtdParcelas,
                                'valor_unitario' => $valorParcela,
                                'quantidade' => 1,
                                'desconto' => 0,
                                'tipo_desconto' => 'absoluto',
                            ]);
                        }
                    });

                    Notification::make()
                        ->title('Faturas geradas com sucesso!')
                        ->body(
                            ($valorEntrada > 0 ? '1 fatura de entrada + ' : '').
                            $qtdParcelas.' parcela(s) criada(s). 1ª parcela: '.$primeiroVencimento->format('d/m/Y').'.'
                        )
                        ->success()
                        ->send();
                }),

            DeleteAction::make(),
        ];
    }
}
