<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransacaoBancaria extends Model
{
    protected $table = 'transacao_bancarias';

    protected $fillable = ['banco_id', 'fatura_id', 'plano_conta_id', 'centro_custo_id', 'fornecedor_id', 'tipo', 'valor', 'data_transacao', 'descricao', 'conciliado', 'external_id'];

    public function banco(): BelongsTo
    {
        return $this->belongsTo(Banco::class);
    }

    public function fatura(): BelongsTo
    {
        return $this->belongsTo(Fatura::class);
    }

    public function planoConta(): BelongsTo
    {
        return $this->belongsTo(PlanoConta::class);
    }

    public function centroCusto(): BelongsTo
    {
        return $this->belongsTo(CentroCusto::class);
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class);
    }
}
