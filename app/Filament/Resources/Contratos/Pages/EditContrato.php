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
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\DB;

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
                ->schema([
                    TextInput::make('quantidade_parcelas')
                        ->label('Quantidade de Parcelas')
                        ->helperText('Número de parcelas em que o valor restante (após entrada) será dividido.')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->required(),
                    TextInput::make('dia_vencimento')
                        ->label('Dia de Vencimento')
                        ->helperText('Dia do mês em que cada parcela vencerá (1 a 28).')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->maxValue(28)
                        ->required(),
                    TextInput::make('valor_entrada')
                        ->label('Valor de Entrada (R$)')
                        ->helperText('Informe 0 caso não haja entrada.')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('R$')
                        ->required()
                        ->live(),
                    TextInput::make('valor_parcela_preview')
                        ->label('Valor por Parcela (prévia)')
                        ->prefix('R$')
                        ->disabled()
                        ->dehydrated(false)
                        ->state(function (Get $get): string {
                            $total = (float) ($this->record->valor_total ?? 0);
                            $entrada = (float) ($get('valor_entrada') ?? 0);
                            $qtd = (int) ($get('quantidade_parcelas') ?? 0);

                            if ($qtd <= 0) {
                                return 'Informe a quantidade de parcelas';
                            }

                            $restante = $total - $entrada;

                            if ($restante < 0) {
                                return 'Entrada maior que o valor do contrato';
                            }

                            return number_format($restante / $qtd, 2, ',', '.');
                        }),
                ])
                ->modalHeading('Gerar Faturas Automaticamente')
                ->modalSubmitActionLabel('Gerar Faturas')
                ->action(function (array $data): void {
                    $contrato = $this->record;

                    if (! $contrato->data_aceite) {
                        Notification::make()
                            ->title('Erro')
                            ->body('O contrato não possui data de aceite definida.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $qtdParcelas = (int) $data['quantidade_parcelas'];
                    $diaVencimento = (int) $data['dia_vencimento'];
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

                    // Data base: mês seguinte ao data_aceite, no dia escolhido
                    $dataAceite = Carbon::parse($contrato->data_aceite);
                    $primeiroVencimento = $dataAceite->copy()->addMonth()->day($diaVencimento);

                    DB::transaction(function () use ($contrato, $valorEntrada, $valorParcela, $qtdParcelas, $primeiroVencimento): void {
                        // Remove faturas existentes e seus itens
                        $contrato->faturas()->each(function (Fatura $fatura): void {
                            $fatura->itens()->delete();
                            $fatura->delete();
                        });

                        // Fatura de entrada (mesmo que seja R$ 0,00, criamos para consistência)
                        if ($valorEntrada > 0) {
                            /** @var Fatura $faturaEntrada */
                            $faturaEntrada = Fatura::create([
                                'contrato_id' => $contrato->id,
                                'vencimento' => $primeiroVencimento->copy()->subMonth(),
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

                        // Parcelas mensais
                        for ($i = 0; $i < $qtdParcelas; $i++) {
                            $vencimento = $primeiroVencimento->copy()->addMonths($i);

                            /** @var Fatura $fatura */
                            $fatura = Fatura::create([
                                'contrato_id' => $contrato->id,
                                'vencimento' => $vencimento,
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
                            $qtdParcelas.' parcela(s) criada(s).'
                        )
                        ->success()
                        ->send();
                }),

            DeleteAction::make(),
        ];
    }
}
