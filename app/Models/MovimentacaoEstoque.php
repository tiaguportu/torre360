<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoMovimentacaoEstoque;
use App\Services\EstoqueService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Entrada, saída ou ajuste de um item de estoque (Onda 16, fase 2). Só o {@see EstoqueService} grava; a quantidade tem
 * sinal e `saldo_apos` é o saldo do item logo depois da movimentação.
 */
class MovimentacaoEstoque extends Model
{
    protected $table = 'movimentacoes_estoque';

    protected $fillable = ['item_estoque_id', 'tipo', 'quantidade', 'saldo_apos', 'custo_unitario', 'centro_custo_id', 'motivo', 'origem_type', 'origem_id', 'user_id'];

    protected function casts(): array
    {
        return [
            'tipo' => TipoMovimentacaoEstoque::class,
            'quantidade' => 'decimal:3',
            'saldo_apos' => 'decimal:3',
            'custo_unitario' => 'decimal:4',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemEstoque::class, 'item_estoque_id');
    }

    public function centroCusto(): BelongsTo
    {
        return $this->belongsTo(CentroCusto::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function origem(): MorphTo
    {
        return $this->morphTo();
    }
}
