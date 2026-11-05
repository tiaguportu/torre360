<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemFatura extends Model
{
    protected $fillable = ['fatura_id', 'descricao', 'valor_unitario', 'quantidade', 'desconto', 'tipo_desconto'];

    public function fatura(): BelongsTo
    {
        return $this->belongsTo(Fatura::class);
    }

    /**
     * Total do item depois do desconto, em reais e já em centavos inteiros. É a regra única do valor de uma fatura
     * (`Fatura::valor`, formulário de edição): o desconto percentual deixa fração de centavo e, sem arredondar, o
     * saldo da fatura paga em dia sobrava em 0,000000001 e ela ficava "Parcial". O arredondamento é o mesmo do
     * `number_format`, então o valor interno passa a ser exatamente o que a família vê e paga.
     *
     * @param  float|int|string|null  $desconto  em reais quando o tipo é `absoluto`; em % nos demais
     */
    public static function calcularTotalLiquido(float|int|string|null $valorUnitario, float|int|string|null $quantidade, float|int|string|null $desconto, ?string $tipoDesconto): float
    {
        $bruto = (float) $valorUnitario * (float) $quantidade;
        $desconto = (float) $desconto;

        $liquido = $tipoDesconto === 'absoluto'
            ? $bruto - $desconto
            : $bruto - ($bruto * ($desconto / 100));

        return round($liquido, 2);
    }

    /**
     * Total do item sem desconto, arredondado a centavos.
     */
    public function totalBruto(): float
    {
        return round((float) $this->valor_unitario * (float) $this->quantidade, 2);
    }

    public function totalLiquido(): float
    {
        return self::calcularTotalLiquido($this->valor_unitario, $this->quantidade, $this->desconto, $this->tipo_desconto);
    }
}
