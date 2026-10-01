<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Coordenador extends Model
{
    protected $table = 'coordenador';

    protected $fillable = ['curso_id', 'pessoa_id', 'cargo', 'data_inicio', 'flag_somente_leitura'];

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class);
    }
}
