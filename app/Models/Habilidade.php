<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Habilidade extends Model
{
    protected $table = 'habilidade';
    protected $guarded = [];

    public function serie(): BelongsTo
    {
        return $this->belongsTo(Serie::class);
    }

    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class);
    }
}
