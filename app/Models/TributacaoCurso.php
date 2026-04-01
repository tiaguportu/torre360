<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TributacaoCurso extends Model
{
    protected $table = 'tributacao_curso';
    protected $guarded = [];

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }
}
