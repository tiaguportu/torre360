<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class TurmaDisciplina extends Pivot
{
    protected $table = 'turma_disciplina';

    public function professor(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'professor_id');
    }
}
