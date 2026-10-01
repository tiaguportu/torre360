<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EtapaAvaliativa extends Model
{
    protected $table = 'etapa_avaliativa';

    protected $fillable = ['turma_id', 'nome', 'data_inicio', 'data_fim', 'periodo_letivo_id'];

    public function periodoLetivo(): BelongsTo
    {
        return $this->belongsTo(PeriodoLetivo::class);
    }

    public function avaliacoes(): HasMany
    {
        return $this->hasMany(Avaliacao::class, 'etapa_avaliativa_id');
    }
}
