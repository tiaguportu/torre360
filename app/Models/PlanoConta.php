<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanoConta extends Model
{
    protected $table = 'plano_contas';

    protected $guarded = [];

    public function pai(): BelongsTo
    {
        return $this->belongsTo(PlanoConta::class, 'pai_id');
    }

    public function filhos(): HasMany
    {
        return $this->hasMany(PlanoConta::class, 'pai_id');
    }

    public function transacoes(): HasMany
    {
        return $this->hasMany(TransacaoBancaria::class);
    }
}
