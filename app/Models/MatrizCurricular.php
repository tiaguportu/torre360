<?php

namespace App\Models;

use Database\Factories\MatrizCurricularFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Grade curricular de referência: define, por série, quais disciplinas devem
 * compor as turmas e a carga horária semanal esperada. É a origem do vínculo
 * disciplina-turma (`turma_disciplina`), que continua editável manualmente.
 */
class MatrizCurricular extends Model
{
    /** @use HasFactory<MatrizCurricularFactory> */
    use HasFactory;

    protected $table = 'matriz_curricular';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'obrigatoria' => 'boolean',
        ];
    }

    public function serie(): BelongsTo
    {
        return $this->belongsTo(Serie::class);
    }

    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class);
    }
}
