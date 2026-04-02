<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FrequenciaEscolar extends Model
{
    protected $table = 'frequencia_escolar';

    protected $guarded = [];

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    public function cronogramaAula(): BelongsTo
    {
        return $this->belongsTo(CronogramaAula::class);
    }
}
