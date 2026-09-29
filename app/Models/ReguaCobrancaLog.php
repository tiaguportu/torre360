<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReguaCobrancaLog extends Model
{
    use HasFactory;

    protected $table = 'regua_cobranca_logs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data_envio' => 'date',
        ];
    }

    public function reguaCobranca(): BelongsTo
    {
        return $this->belongsTo(ReguaCobranca::class);
    }

    public function fatura(): BelongsTo
    {
        return $this->belongsTo(Fatura::class);
    }

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class);
    }
}
