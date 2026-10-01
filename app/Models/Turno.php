<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Turno extends Model
{
    protected $table = 'turno';

    protected $fillable = ['nome', 'hora_inicio', 'hora_fim'];

    public function turmas(): HasMany
    {
        return $this->hasMany(Turma::class);
    }
}
