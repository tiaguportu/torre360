<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeriodoLetivo extends Model
{
    protected $table = 'periodo_letivo';

    protected $guarded = [];

    public function turmas(): HasMany
    {
        return $this->hasMany(Turma::class);
    }

    public function etapasAvaliativas(): HasMany
    {
        return $this->hasMany(EtapaAvaliativa::class);
    }

    public function diasNaoLetivos(): HasMany
    {
        return $this->hasMany(DiaNaoLetivo::class);
    }
}
