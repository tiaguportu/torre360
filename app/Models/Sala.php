<?php

namespace App\Models;

use Database\Factories\SalaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ambiente físico (sala de aula, laboratório, quadra etc.) de uma unidade,
 * usado para alocar horários na grade e evitar duplo agendamento do espaço.
 *
 * Não confundir com o "ensalamento" já existente no sistema
 * (`EnsalamentoService`), que distribui alunos entre turmas.
 */
class Sala extends Model
{
    /** @use HasFactory<SalaFactory> */
    use HasFactory;

    protected $table = 'sala';

    protected $fillable = ['unidade_id', 'nome', 'capacidade', 'tipo', 'ativa'];

    protected function casts(): array
    {
        return [
            'ativa' => 'boolean',
        ];
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function gradeHorarios(): HasMany
    {
        return $this->hasMany(GradeHorario::class);
    }

    public function scopeAtivas(Builder $query): Builder
    {
        return $query->where('ativa', true);
    }
}
