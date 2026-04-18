<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Questionario extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'titulo',
        'descricao',
        'inicio_aplicacao',
        'fim_aplicacao',
        'is_anonimo',
        'is_ativo',
    ];

    protected $casts = [
        'inicio_aplicacao' => 'datetime',
        'fim_aplicacao' => 'datetime',
        'is_anonimo' => 'boolean',
        'is_ativo' => 'boolean',
    ];

    public function blocos(): HasMany
    {
        return $this->hasMany(QuestionarioBloco::class)->orderBy('ordem');
    }

    public function alvos(): HasMany
    {
        return $this->hasMany(QuestionarioAlvo::class);
    }

    public function respostas(): HasMany
    {
        return $this->hasMany(QuestionarioResposta::class);
    }
}
