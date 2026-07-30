<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EtapaEnsino extends Model
{
    use HasFactory;

    protected $table = 'etapa_ensino';

    protected $guarded = [];

    public function etapaEnsinoAgregada(): BelongsTo
    {
        return $this->belongsTo(EtapaEnsinoAgregada::class, 'etapa_ensino_agregada_id');
    }
}
