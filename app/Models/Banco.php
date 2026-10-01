<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Banco extends Model
{
    protected $fillable = ['nome', 'agencia', 'conta', 'pix_key', 'is_active', 'codigo_bacen_id'];

    public function transacoes(): HasMany
    {
        return $this->hasMany(TransacaoBancaria::class);
    }

    public function codigoBacen(): BelongsTo
    {
        return $this->belongsTo(CodigoBacen::class);
    }
}
