<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fatura extends Model
{
    protected $table = 'faturas';

    protected $guarded = [];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(ItemFatura::class);
    }

    public function transacoes(): HasMany
    {
        return $this->hasMany(TransacaoBancaria::class);
    }

    /**
     * Valor total bruto da fatura (soma dos itens sem descontos)
     */
    protected function valorBruto(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->itens->sum(fn ($item) => $item->valor_unitario * $item->quantidade)
        );
    }

    /**
     * Valor total bruto da fatura (soma dos itens com seus respectivos descontos)
     */
    protected function valor(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->itens->reduce(function ($carry, $item) {
                    $totalItem = $item->valor_unitario * $item->quantidade;

                    if ($item->tipo_desconto === 'absoluto') {
                        $totalItem -= $item->desconto;
                    } else {
                        $totalItem -= ($totalItem * ($item->desconto / 100));
                    }

                    return $carry + $totalItem;
                }, 0);
            }
        );
    }

    /**
     * Valor total já pago (soma das transações bancárias vinculadas)
     */
    protected function valorPago(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->transacoes->sum('valor')
        );
    }

    /**
     * Valor que ainda resta ser pago
     */
    protected function valorRestante(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->valor - $this->valor_pago
        );
    }
}
