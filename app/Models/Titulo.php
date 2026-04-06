<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Titulo extends Model
{
    protected $table = 'titulo';

    protected $guarded = [];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }
}
