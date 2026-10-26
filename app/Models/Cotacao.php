<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Proposta de um fornecedor para uma requisição (Onda 38): um preço por item, mais prazo, condições e frete.
 */
class Cotacao extends Model
{
    protected $table = 'cotacoes';

    protected $fillable = ['requisicao_compra_id', 'fornecedor_id', 'prazo_entrega_dias', 'prazo_pagamento_dias', 'condicoes', 'validade_ate', 'frete', 'escolhida', 'motivo_escolha', 'registrada_por_user_id'];

    protected function casts(): array
    {
        return [
            'validade_ate' => 'date',
            'frete' => 'decimal:2',
            'escolhida' => 'boolean',
            'prazo_entrega_dias' => 'integer',
            'prazo_pagamento_dias' => 'integer',
        ];
    }

    public function requisicao(): BelongsTo
    {
        return $this->belongsTo(RequisicaoCompra::class, 'requisicao_compra_id');
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(ItemCotacao::class);
    }

    public function registradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrada_por_user_id');
    }

    /**
     * Total da cotação: preço × quantidade de cada item, mais o frete. Exige `itens.itemRequisicao` carregados.
     */
    public function total(): float
    {
        $itens = $this->itens->sum(fn (ItemCotacao $i): float => (float) $i->preco_unitario * (float) ($i->itemRequisicao?->quantidade ?? 0));

        return round($itens + (float) $this->frete, 2);
    }

    public function vencida(): bool
    {
        return $this->validade_ate !== null && $this->validade_ate->lt(today());
    }
}
