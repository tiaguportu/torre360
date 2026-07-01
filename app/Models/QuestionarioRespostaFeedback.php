<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionarioRespostaFeedback extends Model
{
    protected $table = 'questionario_resposta_feedbacks';

    protected $fillable = [
        'questionario_resposta_id',
        'user_id',
        'texto',
    ];

    public function resposta(): BelongsTo
    {
        return $this->belongsTo(QuestionarioResposta::class, 'questionario_resposta_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
