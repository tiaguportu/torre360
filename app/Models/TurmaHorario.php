<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TurmaHorario extends Model
{
    use HasFactory;

    protected $table = 'turma_horario';

    protected $fillable = ['turma_id', 'dia_semana', 'hora_inicio', 'hora_fim'];

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    protected function casts(): array
    {
        return [
            'dia_semana' => 'integer',
        ];
    }
}
