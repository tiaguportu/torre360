<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Turno extends Model
{
    protected $table = 'turno';

    protected $fillable = ['nome', 'hora_inicio', 'hora_fim'];

    public function turmas(): HasMany
    {
        return $this->hasMany(Turma::class);
    }

    /**
     * Tempos de aula do turno (Onda 64), do primeiro ao último.
     */
    public function temposAula(): HasMany
    {
        return $this->hasMany(TempoAula::class)->orderBy('ordem');
    }
}
