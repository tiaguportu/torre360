<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Banco extends Model
{
    protected $guarded = [];

    public function transacoes(): HasMany
    {
        return $this->hasMany(TransacaoBancaria::class);
    }
}
