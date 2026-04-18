<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionarioResposta extends Model
{
    protected $fillable = [
        'questionario_id',
        'user_id',
        'perfil_institucional',
        'inicio_preenchimento',
        'fim_preenchimento',
        'status',
    ];

    protected $casts = [
        'inicio_preenchimento' => 'datetime',
        'fim_preenchimento' => 'datetime',
    ];

    public function questionario(): BelongsTo
    {
        return $this->belongsTo(Questionario::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function perguntaRespostas(): HasMany
    {
        return $this->hasMany(QuestionarioPerguntaResposta::class, 'questionario_resposta_id');
    }
}
