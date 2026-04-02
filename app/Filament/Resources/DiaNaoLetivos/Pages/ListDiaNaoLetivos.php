<?php

namespace App\Filament\Resources\DiaNaoLetivos\Pages;

use App\Filament\Resources\DiaNaoLetivos\DiaNaoLetivoResource;
use App\Models\DiaNaoLetivo;
use App\Models\PeriodoLetivo;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Carbon;

class ListDiaNaoLetivos extends ListRecords
{
    protected static string $resource = DiaNaoLetivoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('gerarFDSFeriados')
                ->label('Gerar FDS e Feriados')
                ->icon('heroicon-o-calendar-days')
                ->color('info')
                ->form([
                    Select::make('periodo_letivo_id')
                        ->label('Período Letivo')
                        ->options(PeriodoLetivo::all()->pluck('nome', 'id'))
                        ->required(),
                ])
                ->action(function (array $data) {
                    $periodo = PeriodoLetivo::findOrFail($data['periodo_letivo_id']);
                    $start = Carbon::parse($periodo->data_inicio);
                    $end = Carbon::parse($periodo->data_fim);
                    $count = 0;

                    $current = $start->copy();
                    while ($current <= $end) {
                        $descricao = null;
                        if ($current->isSaturday()) {
                            $descricao = 'Sábado';
                        } elseif ($current->isSunday()) {
                            $descricao = 'Domingo';
                        } else {
                            $feriado = DiaNaoLetivo::getFeriadoNacional($current);
                            if ($feriado) {
                                $descricao = $feriado;
                            }
                        }

                        if ($descricao) {
                            DiaNaoLetivo::updateOrCreate(
                                [
                                    'periodo_letivo_id' => $periodo->id,
                                    'data' => $current->toDateString(),
                                ],
                                [
                                    'descricao' => $descricao,
                                    'flag_ativo' => true,
                                ]
                            );
                            $count++;
                        }
                        $current->addDay();
                    }

                    Notification::make()
                        ->title("{$count} dias não letivos criados/atualizados!")
                        ->success()
                        ->send();
                })
                ->tooltip('Cria automaticamente finais de semana (Sábados/Domingos) e Feriados Nacionais Brasileiros considerados: 01/01, 21/04, 01/05, 07/09, 12/10, 02/11, 15/11, 20/11, 25/12 e Sexta-Feira Santa.'),
            CreateAction::make(),
        ];
    }
}
