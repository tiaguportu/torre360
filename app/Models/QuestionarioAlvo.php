<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionarioAlvo extends Model
{
    protected $fillable = [
        'questionario_id',
        'alvo_type',
        'alvo_id',
    ];

    public function questionario(): BelongsTo
    {
        return $this->belongsTo(Questionario::class);
    }
}
