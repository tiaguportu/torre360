<?php

namespace App\Filament\Resources\Faturas\Pages;

use App\Filament\Resources\Faturas\FaturaResource;
use App\Models\Contrato;
use App\Models\Fatura;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListFaturas extends ListRecords
{
    protected static string $resource = FaturaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('gerarLote')
                ->label('Criação em Lote')
                ->icon('heroicon-o-rectangle-stack')
                ->color('primary')
                ->form([
                    Select::make('contrato_id')
                        ->label('Contrato')
                        ->options(Contrato::all()->mapWithKeys(fn ($c) => [$c->id => "Nº {$c->id} - Valor: R$ ".number_format($c->valor_total, 2, ',', '.')]))
                        ->searchable()
                        ->required(),
                    TextInput::make('parcelas')
                        ->label('Quantidade de Parcelas')
                        ->numeric()
                        ->default(12)
                        ->required(),
                    Toggle::make('por_matricula')
                        ->label('Criar uma fatura por cada matrícula?')
                        ->default(true)
                        ->helperText('Se marcado, o valor total será dividido pelo número de matrículas e parcelas.'),
                ])
                ->action(function (array $data) {
                    $contrato = Contrato::with('matriculas.turma.periodoLetivo')->find($data['contrato_id']);
                    if (! $contrato) {
                        return;
                    }

                    $numParcelas = (int) $data['parcelas'];
                    $valorTotal = $contrato->valor_total;
                    $startDate = Carbon::parse($contrato->data_aceite);

                    $matriculas = $contrato->matriculas;
                    $numMatriculas = $matriculas->count() ?: 1;

                    if ($data['por_matricula']) {
                        // Cria parcelas para cada matrícula
                        $valorPorFatura = $valorTotal / ($numMatriculas * $numParcelas);

                        foreach ($matriculas as $matricula) {
                            $startDate = $contrato->data_aceite ? Carbon::parse($contrato->data_aceite)->startOfDay() : now()->startOfDay();

                            // Se não houver data_fim no período letivo, usamos o fallback de meses
                            $origEndDate = ($matricula->turma?->periodoLetivo?->data_fim)
                                ? Carbon::parse($matricula->turma->periodoLetivo->data_fim)->startOfDay()
                                : $startDate->copy()->addMonths($numParcelas);

                            // O último vencimento NÃO PODE ser superior a data_fim do PeriodoLetivo
                            // Se a data_fim for anterior ao contrato, travamos no startDate
                            $endDate = $origEndDate->lt($startDate) ? $startDate->copy() : $origEndDate;

                            $totalIntervalInDays = $endDate->diffInDays($startDate);
                            $intervalBetweenPayments = $numParcelas > 1 ? $totalIntervalInDays / ($numParcelas - 1) : 0;

                            for ($i = 0; $i < $numParcelas; $i++) {
                                $vencimento = $startDate->copy()->addDays(round($i * $intervalBetweenPayments));

                                Fatura::create([
                                    'contrato_id' => $contrato->id,
                                    'vencimento' => $vencimento,
                                    'valor' => $valorPorFatura,
                                    'status' => 'pendente',
                                ]);
                            }
                        }
                    } else {
                        // Cria parcelas globais para o contrato
                        $valorPorFatura = $valorTotal / $numParcelas;

                        $startDate = $contrato->data_aceite ? Carbon::parse($contrato->data_aceite)->startOfDay() : now()->startOfDay();

                        $origEndDate = ($matriculas->first()?->turma?->periodoLetivo?->data_fim)
                            ? Carbon::parse($matriculas->first()->turma->periodoLetivo->data_fim)->startOfDay()
                            : $startDate->copy()->addMonths($numParcelas);

                        $endDate = $origEndDate->lt($startDate) ? $startDate->copy() : $origEndDate;

                        $totalIntervalInDays = $endDate->diffInDays($startDate);
                        $intervalBetweenPayments = $numParcelas > 1 ? $totalIntervalInDays / ($numParcelas - 1) : 0;

                        for ($i = 0; $i < $numParcelas; $i++) {
                            $vencimento = $startDate->copy()->addDays(round($i * $intervalBetweenPayments));

                            Fatura::create([
                                'contrato_id' => $contrato->id,
                                'vencimento' => $vencimento,
                                'valor' => $valorPorFatura,
                                'status' => 'pendente',
                            ]);
                        }
                    }

                    Notification::make()
                        ->title('Faturas geradas com sucesso!')
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
