<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EtapaEnsinoAgregada extends Model
{
    use HasFactory;

    protected $table = 'etapa_ensino_agregada';

    protected $guarded = [];

    public function etapasEnsino(): HasMany
    {
        return $this->hasMany(EtapaEnsino::class, 'etapa_ensino_agregada_id');
    }
}
