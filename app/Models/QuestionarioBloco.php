<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionarioBloco extends Model
{
    protected $fillable = [
        'questionario_id',
        'titulo',
        'descricao',
        'ordem',
    ];

    public function questionario(): BelongsTo
    {
        return $this->belongsTo(Questionario::class);
    }

    public function perguntas(): HasMany
    {
        return $this->hasMany(QuestionarioPergunta::class)->orderBy('ordem');
    }
}
