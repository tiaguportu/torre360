<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class QuestionarioResponsavel extends Model
{
    protected $table = 'questionario_responsaveis';

    protected $fillable = [
        'questionario_id',
        'responsavel_id',
        'responsavel_type',
        'nivel',
    ];

    public function questionario(): BelongsTo
    {
        return $this->belongsTo(Questionario::class);
    }

    public function responsavel(): MorphTo
    {
        return $this->morphTo();
    }
}
