<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CentroCusto extends Model
{
    protected $table = 'centro_custos';

    protected $guarded = [];

    public function transacoes(): HasMany
    {
        return $this->hasMany(TransacaoBancaria::class);
    }
}
