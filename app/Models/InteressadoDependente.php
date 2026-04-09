<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InteressadoDependente extends Model
{
    protected $table = 'interessado_dependente';

    protected $guarded = [];

    public function interessado(): BelongsTo
    {
        return $this->belongsTo(Interessado::class);
    }

    public function serie(): BelongsTo
    {
        return $this->belongsTo(Serie::class);
    }
}
