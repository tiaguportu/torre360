<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CronogramaAula extends Model
{
    protected $table = 'cronograma_aula';
    protected $guarded = [];

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
        return $this->belongsTo(Pessoa::class, 'pessoa_id');
    }
}
