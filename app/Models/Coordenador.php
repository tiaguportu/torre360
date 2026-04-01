<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Coordenador extends Model
{
    protected $table = 'coordenador';
    protected $guarded = [];

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class);
    }
}
