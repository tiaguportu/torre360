<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Estado extends Model
{
    protected $table = 'estado';
    protected $guarded = [];

    public function pais(): BelongsTo
    {
        return $this->belongsTo(Pais::class);
    }

    public function cidades(): HasMany
    {
        return $this->hasMany(Cidade::class);
    }
}
