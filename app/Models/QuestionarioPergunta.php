<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionarioPergunta extends Model
{
    protected $fillable = [
        'questionario_bloco_id',
        'enunciado',
        'tipo',
        'opcoes',
        'is_obrigatoria',
        'ordem',
    ];

    protected $casts = [
        'opcoes' => 'json',
        'is_obrigatoria' => 'boolean',
    ];

    public function bloco(): BelongsTo
    {
        return $this->belongsTo(QuestionarioBloco::class, 'questionario_bloco_id');
    }
}
