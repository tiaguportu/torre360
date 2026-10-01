<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemFatura extends Model
{
    protected $fillable = ['fatura_id', 'descricao', 'valor_unitario', 'quantidade', 'desconto', 'tipo_desconto'];

    public function fatura(): BelongsTo
    {
        return $this->belongsTo(Fatura::class);
    }
}
