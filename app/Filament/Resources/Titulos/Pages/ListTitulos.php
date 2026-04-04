<?php

namespace App\Filament\Resources\Titulos\Pages;

use App\Filament\Resources\Titulos\TituloResource;
use App\Models\Contrato;
use App\Models\Titulo;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListTitulos extends ListRecords
{
    protected static string $resource = TituloResource::class;

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
                        ->label('Criar um título por cada matrícula?')
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
                        $valorPorTitulo = $valorTotal / ($numMatriculas * $numParcelas);

                        foreach ($matriculas as $matricula) {
                            $endDate = $matricula->turma?->periodoLetivo?->data_fim
                                ? Carbon::parse($matricula->turma->periodoLetivo->data_fim)
                                : $startDate->copy()->addYear();

                            $totalIntervalInDays = $endDate->diffInDays($startDate);
                            $intervalBetweenPayments = $numParcelas > 1 ? $totalIntervalInDays / ($numParcelas - 1) : 0;

                            for ($i = 0; $i < $numParcelas; $i++) {
                                $vencimento = $startDate->copy()->addDays(round($i * $intervalBetweenPayments));

                                Titulo::create([
                                    'contrato_id' => $contrato->id,
                                    'vencimento' => $vencimento,
                                    'valor' => $valorPorTitulo,
                                    'status' => 'pendente',
                                ]);
                            }
                        }
                    } else {
                        // Cria parcelas globais para o contrato
                        $valorPorTitulo = $valorTotal / $numParcelas;

                        // Busca a data fim da primeira matrícula ou padrão 1 ano
                        $endDate = $matriculas->first()?->turma?->periodoLetivo?->data_fim
                            ? Carbon::parse($matriculas->first()->turma->periodoLetivo->data_fim)
                            : $startDate->copy()->addYear();

                        $totalIntervalInDays = $endDate->diffInDays($startDate);
                        $intervalBetweenPayments = $numParcelas > 1 ? $totalIntervalInDays / ($numParcelas - 1) : 0;

                        for ($i = 0; $i < $numParcelas; $i++) {
                            $vencimento = $startDate->copy()->addDays(round($i * $intervalBetweenPayments));

                            Titulo::create([
                                'contrato_id' => $contrato->id,
                                'vencimento' => $vencimento,
                                'valor' => $valorPorTitulo,
                                'status' => 'pendente',
                            ]);
                        }
                    }

                    Notification::make()
                        ->title('Títulos gerados com sucesso!')
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
