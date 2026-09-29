<?php

namespace App\Models;

use Database\Factories\GradeHorarioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um horário recorrente da grade semanal de uma turma: uma disciplina, num dia
 * da semana, entre dois horários, com professor e sala (opcionais). É o
 * modelo de origem para gerar o cronograma de aulas datado de um período letivo.
 */
class GradeHorario extends Model
{
    /** @use HasFactory<GradeHorarioFactory> */
    use HasFactory;

    protected $table = 'grade_horario';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'dia_semana' => 'integer',
        ];
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class);
    }

    public function professor(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'professor_id');
    }

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class);
    }

    /**
     * Nomes dos dias da semana na convenção já usada em `turma_horario`
     * (0=Domingo ... 6=Sábado).
     */
    public function nomeDiaSemana(): string
    {
        return [
            0 => 'Domingo',
            1 => 'Segunda-feira',
            2 => 'Terça-feira',
            3 => 'Quarta-feira',
            4 => 'Quinta-feira',
            5 => 'Sexta-feira',
            6 => 'Sábado',
        ][$this->dia_semana] ?? '—';
    }
}
