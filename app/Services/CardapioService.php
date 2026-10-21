<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cardapio;
use App\Models\User;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class CardapioService
{
    /**
     * Copia as refeições de uma semana para outra como rascunho (a publicação é uma decisão à parte). Refeições
     * que já existem no destino (mesma data, tipo e unidade) são preservadas, nunca sobrescritas.
     *
     * @return int Quantas refeições foram criadas.
     *
     * @throws InvalidArgumentException
     */
    public function duplicarSemana(CarbonInterface $semanaOrigem, CarbonInterface $semanaDestino, ?int $unidadeId, User $por): int
    {
        $segundaOrigem = $semanaOrigem->copy()->startOfWeek();
        $segundaDestino = $semanaDestino->copy()->startOfWeek();

        if ($segundaOrigem->isSameDay($segundaDestino)) {
            throw new InvalidArgumentException('A semana de destino precisa ser diferente da semana de origem.');
        }

        $deslocamentoDias = (int) $segundaOrigem->diffInDays($segundaDestino, false);

        $origem = Cardapio::query()
            ->daSemana($segundaOrigem)
            ->when($unidadeId, fn ($q) => $q->where('unidade_id', $unidadeId), fn ($q) => $q->whereNull('unidade_id'))
            ->get();

        $criadas = 0;

        foreach ($origem as $refeicao) {
            $novaData = $refeicao->data->copy()->addDays($deslocamentoDias);

            $jaExiste = Cardapio::query()
                ->whereDate('data', $novaData->toDateString())
                ->where('tipo_refeicao', $refeicao->tipo_refeicao->value)
                ->where('unidade_chave', (int) ($refeicao->unidade_id ?? 0))
                ->exists();

            if ($jaExiste) {
                continue;
            }

            Cardapio::create([
                'data' => $novaData->toDateString(),
                'tipo_refeicao' => $refeicao->tipo_refeicao,
                'descricao' => $refeicao->descricao,
                'alergenos' => $refeicao->alergenos,
                'unidade_id' => $refeicao->unidade_id,
                'publicado' => false,
                'observacoes' => $refeicao->observacoes,
                'criado_por_user_id' => $por->id,
            ]);

            $criadas++;
        }

        return $criadas;
    }
}
