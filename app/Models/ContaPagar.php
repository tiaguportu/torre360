<?php

namespace App\Models;

use App\Enums\StatusContaPagar;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContaPagar extends Model
{
    use HasFactory;

    protected $table = 'conta_pagars';

    protected $fillable = [
        'descricao',
        'valor',
        'vencimento',
        'status',
        'data_pagamento',
        'fornecedor_id',
        'plano_conta_id',
        'centro_custo_id',
        'transacao_bancaria_id',
        'observacao',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusContaPagar::class,
            'vencimento' => 'date',
            'data_pagamento' => 'date',
        ];
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class);
    }

    public function planoConta(): BelongsTo
    {
        return $this->belongsTo(PlanoConta::class);
    }

    public function centroCusto(): BelongsTo
    {
        return $this->belongsTo(CentroCusto::class);
    }

    public function transacaoBancaria(): BelongsTo
    {
        return $this->belongsTo(TransacaoBancaria::class);
    }

    public function getDiasAtrasoAttribute(): int
    {
        if ($this->status !== StatusContaPagar::Atrasado && $this->status !== StatusContaPagar::Pendente) {
            return 0;
        }

        return max(0, (int) $this->vencimento->copy()->startOfDay()->diffInDays(now()->startOfDay(), false));
    }

    /**
     * Marca como Atrasado qualquer conta Pendente cujo vencimento já passou — mesmo
     * princípio de `ReguaCobrancaService::atualizarStatusFaturasAtrasadas()`, aplicado às
     * contas a pagar.
     */
    public static function atualizarAtrasadas(): int
    {
        return static::where('vencimento', '<', now()->toDateString())
            ->where('status', StatusContaPagar::Pendente)
            ->update(['status' => StatusContaPagar::Atrasado]);
    }
}
