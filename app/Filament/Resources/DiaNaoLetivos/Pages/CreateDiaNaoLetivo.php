<?php

namespace App\Filament\Resources\DiaNaoLetivos\Pages;

use App\Filament\Resources\DiaNaoLetivos\DiaNaoLetivoResource;
use App\Models\DiaNaoLetivo;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Carbon;

class CreateDiaNaoLetivo extends CreateRecord
{
    protected static string $resource = DiaNaoLetivoResource::class;

    protected function afterCreate(): void
    {
        $data = $this->form->getRawState();

        if (! isset($data['data_fim']) || ! $data['data_fim']) {
            return;
        }

        $periodoLetivoId = $data['periodo_letivo_id'];
        $descricaoBase = $data['descricao'];
        $flagAtivo = $data['flag_ativo'];
        $dataInicio = Carbon::parse($data['data'])->addDay();
        $dataFim = Carbon::parse($data['data_fim']);

        $currentDate = $dataInicio;

        while ($currentDate->lessThanOrEqualTo($dataFim)) {
            // Verifica a descrição para cada dia específico se for fim de semana ou feriado
            $descricao = $descricaoBase;
            if ($currentDate->isSaturday()) {
                $descricao = 'Sábado';
            } elseif ($currentDate->isSunday()) {
                $descricao = 'Domingo';
            } else {
                $feriado = DiaNaoLetivo::getFeriadoNacional($currentDate);
                if ($feriado) {
                    $descricao = $feriado;
                }
            }

            // Cria o registro se não existir um para a mesma data no mesmo período letivo
            // para evitar erro de restrição de unicidade que adicionamos anteriormente
            DiaNaoLetivo::updateOrCreate(
                [
                    'periodo_letivo_id' => $periodoLetivoId,
                    'data' => $currentDate->toDateString(),
                ],
                [
                    'descricao' => $descricao,
                    'flag_ativo' => $flagAtivo,
                ]
            );

            $currentDate->addDay();
        }
    }
}
