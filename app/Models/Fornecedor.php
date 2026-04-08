<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fornecedor extends Model
{
    protected $table = 'fornecedors';

    protected $guarded = [];

    public function transacoes(): HasMany
    {
        return $this->hasMany(TransacaoBancaria::class);
    }
}
