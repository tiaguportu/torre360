<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DestinoItemCompra;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemRequisicaoCompra extends Model
{
    protected $table = 'itens_requisicao_compra';

    protected $fillable = ['requisicao_compra_id', 'descricao', 'quantidade', 'unidade_medida', 'estimativa_unitaria', 'destino', 'item_estoque_id', 'ordem'];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'estimativa_unitaria' => 'decimal:2',
            'destino' => DestinoItemCompra::class,
        ];
    }

    public function requisicao(): BelongsTo
    {
        return $this->belongsTo(RequisicaoCompra::class, 'requisicao_compra_id');
    }

    public function itemEstoque(): BelongsTo
    {
        return $this->belongsTo(ItemEstoque::class);
    }

    public function itensRecebimento(): HasMany
    {
        return $this->hasMany(ItemRecebimentoCompra::class, 'item_requisicao_compra_id');
    }

    /**
     * Quanto já foi recebido deste item, somando todos os recebimentos.
     */
    public function quantidadeRecebida(): float
    {
        return round((float) $this->itensRecebimento()->sum('quantidade_recebida'), 3);
    }

    public function quantidadePendente(): float
    {
        return max(0.0, round((float) $this->quantidade - $this->quantidadeRecebida(), 3));
    }
}
