<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pedido de compra (Onda 38): nasce quando a cotação vencedora é escolhida e guarda o valor, a alçada exigida, quem aprovou e o saldo
 * do orçamento no momento da aprovação.
 */
class PedidoCompra extends Model
{
    protected $table = 'pedidos_compra';

    protected $fillable = ['numero', 'requisicao_compra_id', 'cotacao_id', 'fornecedor_id', 'valor_total', 'nivel_alcada', 'aprovado_por_user_id', 'aprovado_em', 'justificativa_acima_do_orcamento', 'saldo_orcamentario'];

    protected function casts(): array
    {
        return [
            'valor_total' => 'decimal:2',
            'nivel_alcada' => 'integer',
            'aprovado_em' => 'datetime',
            'saldo_orcamentario' => 'array',
        ];
    }

    public function requisicao(): BelongsTo
    {
        return $this->belongsTo(RequisicaoCompra::class, 'requisicao_compra_id');
    }

    public function cotacao(): BelongsTo
    {
        return $this->belongsTo(Cotacao::class);
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class);
    }

    public function aprovadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprovado_por_user_id');
    }

    public function recebimentos(): HasMany
    {
        return $this->hasMany(RecebimentoCompra::class)->orderBy('id');
    }

    public function contasPagar(): HasMany
    {
        return $this->hasMany(ContaPagar::class);
    }

    public function foiAprovado(): bool
    {
        return $this->aprovado_em !== null;
    }
}
