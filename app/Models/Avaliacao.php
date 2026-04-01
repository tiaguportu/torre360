<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Avaliacao extends Model
{
    protected $table = 'avaliacao';
    protected $guarded = [];

    protected $casts = [
        'data_prevista' => 'date',
        'nota_maxima' => 'decimal:2',
        'peso_etapa_avaliativa' => 'decimal:2',
    ];

    public function etapaAvaliativa(): BelongsTo
    {
        return $this->belongsTo(EtapaAvaliativa::class, 'etapa_avaliativa_id');
    }

    public function notas(): HasMany
    {
        return $this->hasMany(Nota::class);
    }

    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class);
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function professor(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'professor_id');
    }
}
