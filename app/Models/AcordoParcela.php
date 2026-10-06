<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcordoParcela extends Model
{
    use HasFactory;

    protected $table = 'acordo_parcelas';

    protected $fillable = [
        'acordo_inadimplencia_id',
        'numero_parcela',
        'valor',
        'data_vencimento',
        'data_pagamento',
        'valor_pago',
        'status',
        'forma_pagamento',
        'fatura_gerada_id',
    ];

    protected function casts(): array
    {
        return [
            'numero_parcela' => 'integer',
            'valor' => 'decimal:2',
            'valor_pago' => 'decimal:2',
            'data_vencimento' => 'date',
            'data_pagamento' => 'date',
        ];
    }

    public function acordo(): BelongsTo
    {
        return $this->belongsTo(AcordoInadimplencia::class, 'acordo_inadimplencia_id');
    }

    public function fatura(): BelongsTo
    {
        return $this->belongsTo(Fatura::class, 'fatura_gerada_id');
    }

    public function isPaga(): bool
    {
        return $this->status === 'pago';
    }
}
