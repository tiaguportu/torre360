<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cidade extends Model
{
    protected $table = 'cidade';

    protected $guarded = [];

    public function estado(): BelongsTo
    {
        return $this->belongsTo(Estado::class);
    }

    public function enderecos(): HasMany
    {
        return $this->hasMany(Endereco::class);
    }

    public function pessoas(): HasMany
    {
        return $this->hasMany(Pessoa::class, 'naturalidade_id');
    }
}
