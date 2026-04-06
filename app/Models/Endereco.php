<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Endereco extends Model
{
    protected $table = 'endereco';

    protected $guarded = [];

    public function cidade(): BelongsTo
    {
        return $this->belongsTo(Cidade::class);
    }

    public function pessoas(): HasMany
    {
        return $this->hasMany(Pessoa::class);
    }

    public function unidades(): HasMany
    {
        return $this->hasMany(Unidade::class);
    }
}
