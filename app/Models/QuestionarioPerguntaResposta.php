<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionarioPerguntaResposta extends Model
{
    protected $table = 'questionario_pergunta_respostas';

    protected $fillable = [
        'questionario_resposta_id',
        'questionario_pergunta_id',
        'resposta_texto',
        'resposta_json',
    ];

    protected $casts = [
        'resposta_json' => 'json',
    ];

    public function respostaPai(): BelongsTo
    {
        return $this->belongsTo(QuestionarioResposta::class, 'questionario_resposta_id');
    }

    public function pergunta(): BelongsTo
    {
        return $this->belongsTo(QuestionarioPergunta::class, 'questionario_pergunta_id');
    }
}
