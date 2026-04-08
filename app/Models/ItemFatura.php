<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemFatura extends Model
{
    protected $guarded = [];

    public function fatura(): BelongsTo
    {
        return $this->belongsTo(Fatura::class);
    }
}
