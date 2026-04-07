<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class AlunoResponsavel extends Pivot
{
    protected $table = 'aluno_responsavel';

    public function tipoVinculo(): BelongsTo
    {
        return $this->belongsTo(TipoVinculo::class, 'tipo_vinculo_id');
    }

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'aluno_id');
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'responsavel_id');
    }
}
