<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Valor previsto de uma conta do plano (e, opcionalmente, de um centro de custo) em um mês do orçamento (Onda 43).
 */
class OrcamentoLinha extends Model
{
    protected $table = 'orcamento_linhas';

    protected $fillable = ['orcamento_id', 'plano_conta_id', 'centro_custo_id', 'mes', 'valor_previsto'];

    protected function casts(): array
    {
        return [
            'mes' => 'integer',
            'valor_previsto' => 'decimal:2',
        ];
    }

    public function orcamento(): BelongsTo
    {
        return $this->belongsTo(Orcamento::class);
    }

    public function planoConta(): BelongsTo
    {
        return $this->belongsTo(PlanoConta::class);
    }

    public function centroCusto(): BelongsTo
    {
        return $this->belongsTo(CentroCusto::class);
    }
}
