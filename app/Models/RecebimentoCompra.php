<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Recebimento de um pedido (Onda 38), total ou parcial, com a nota fiscal. De cada recebimento nasce uma conta a pagar.
 */
class RecebimentoCompra extends Model
{
    protected $table = 'recebimentos_compra';

    protected $fillable = ['pedido_compra_id', 'recebido_por_user_id', 'data_recebimento', 'nota_fiscal_numero', 'nota_fiscal_path', 'observacao', 'divergencia', 'valor_recebido'];

    protected function casts(): array
    {
        return [
            'data_recebimento' => 'date',
            'divergencia' => 'boolean',
            'valor_recebido' => 'decimal:2',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(PedidoCompra::class, 'pedido_compra_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(ItemRecebimentoCompra::class);
    }

    public function recebidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recebido_por_user_id');
    }

    public function contaPagar(): HasOne
    {
        return $this->hasOne(ContaPagar::class, 'recebimento_compra_id');
    }
}
