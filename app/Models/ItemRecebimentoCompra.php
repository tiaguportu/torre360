<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemRecebimentoCompra extends Model
{
    protected $table = 'itens_recebimento_compra';

    protected $fillable = ['recebimento_compra_id', 'item_requisicao_compra_id', 'quantidade_recebida', 'preco_unitario'];

    protected function casts(): array
    {
        return [
            'quantidade_recebida' => 'decimal:3',
            'preco_unitario' => 'decimal:4',
        ];
    }

    public function recebimento(): BelongsTo
    {
        return $this->belongsTo(RecebimentoCompra::class, 'recebimento_compra_id');
    }

    public function itemRequisicao(): BelongsTo
    {
        return $this->belongsTo(ItemRequisicaoCompra::class, 'item_requisicao_compra_id');
    }
}
