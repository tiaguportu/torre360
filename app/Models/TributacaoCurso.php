<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TributacaoCurso extends Model
{
    protected $table = 'tributacao_curso';

    protected $fillable = ['curso_id', 'cnae', 'iss', 'pis', 'cofins', 'item_servico'];

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }
}
