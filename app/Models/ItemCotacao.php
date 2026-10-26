<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemCotacao extends Model
{
    protected $table = 'itens_cotacao';

    protected $fillable = ['cotacao_id', 'item_requisicao_compra_id', 'preco_unitario'];

    protected function casts(): array
    {
        return ['preco_unitario' => 'decimal:4'];
    }

    public function cotacao(): BelongsTo
    {
        return $this->belongsTo(Cotacao::class);
    }

    public function itemRequisicao(): BelongsTo
    {
        return $this->belongsTo(ItemRequisicaoCompra::class, 'item_requisicao_compra_id');
    }
}
