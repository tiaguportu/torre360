<?php

namespace App\Models;

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

    public function atualizarValorTotal(): void
    {
        $total = 0;
        foreach ($this->itens as $item) {
            $totalItem = $item->valor_unitario * $item->quantidade;
            if ($item->tipo_desconto === 'absoluto') {
                $totalItem -= $item->desconto;
            } else {
                $totalItem -= ($totalItem * ($item->desconto / 100));
            }
            $total += $totalItem;
        }
        $this->update(['valor' => $total]);
    }

    public function getValorRestanteAttribute(): float
    {
        return $this->valor - $this->transacoes->sum('valor');
    }
}
