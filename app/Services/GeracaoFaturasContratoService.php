<?php

namespace App\Services;

use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\ItemFatura;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Gera as faturas (entrada + parcelas) de um contrato. Extraído da ação "Gerar Faturas
 * Automaticamente" de `EditContrato` (que continua chamando este serviço) para ser
 * reutilizado pela efetivação da Rematrícula Online, que precisa gerar a cobrança sem
 * depender de um clique manual da secretaria.
 */
class GeracaoFaturasContratoService
{
    /**
     * Gera a fatura de entrada (se houver) e as parcelas do contrato. Remove faturas
     * existentes do contrato antes de gerar — mesmo comportamento já usado pela ação
     * manual (regenerar substitui, não soma).
     *
     * A 1ª parcela vence 5 dias úteis após a data-base; as demais, uma por mês a partir
     * daí. A fatura de entrada (quando houver) vence na própria data-base.
     *
     * A data-base é `$dataBase` quando informada; senão, `$contrato->data_aceite`. A
     * Rematrícula Online informa a data da confirmação pela família porque o contrato
     * dela só recebe `data_aceite` quando é assinado (depois de as faturas já existirem).
     *
     * @return Collection<int, Fatura>
     */
    public function gerar(Contrato $contrato, int $quantidadeParcelas, float $valorEntrada, ?Carbon $dataBase = null): Collection
    {
        $dataBase ??= $contrato->data_aceite ? Carbon::parse($contrato->data_aceite) : null;

        if (! $dataBase) {
            throw new \InvalidArgumentException("O contrato #{$contrato->id} não possui data de aceite definida.");
        }

        $valorTotal = (float) $contrato->valor_total;
        $valorRestante = $valorTotal - $valorEntrada;

        if ($valorRestante < 0) {
            throw new \InvalidArgumentException('O valor de entrada não pode ser maior que o valor total do contrato.');
        }

        $valorParcela = $quantidadeParcelas > 0 ? round($valorRestante / $quantidadeParcelas, 2) : 0;

        $dataAceite = $dataBase->copy();
        $primeiroVencimento = $this->adicionarDiasUteis($dataAceite, 5);

        return DB::transaction(function () use ($contrato, $valorEntrada, $valorParcela, $quantidadeParcelas, $dataAceite, $primeiroVencimento): Collection {
            $contrato->faturas()->each(function (Fatura $fatura): void {
                $fatura->itens()->delete();
                $fatura->delete();
            });

            $faturas = collect();

            if ($valorEntrada > 0) {
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

                $faturas->push($faturaEntrada);
            }

            for ($i = 0; $i < $quantidadeParcelas; $i++) {
                $vencimento = $primeiroVencimento->copy()->addMonths($i);

                $fatura = Fatura::create([
                    'contrato_id' => $contrato->id,
                    'vencimento' => $vencimento->toDateString(),
                    'status' => 'pendente',
                ]);

                ItemFatura::create([
                    'fatura_id' => $fatura->id,
                    'descricao' => 'Parcela '.($i + 1).' de '.$quantidadeParcelas,
                    'valor_unitario' => $valorParcela,
                    'quantidade' => 1,
                    'desconto' => 0,
                    'tipo_desconto' => 'absoluto',
                ]);

                $faturas->push($fatura);
            }

            return $faturas;
        });
    }

    /**
     * Avança N dias úteis a partir de uma data, pulando sábados e domingos.
     */
    public function adicionarDiasUteis(Carbon $data, int $dias): Carbon
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
}
