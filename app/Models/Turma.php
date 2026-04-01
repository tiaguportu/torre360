<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Turma extends Model
{
    protected $table = 'turma';
    protected $guarded = [];

    public function serie(): BelongsTo
    {
        return $this->belongsTo(Serie::class);
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(Turno::class);
    }

    public function professorConselheiro(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'professor_conselheiro_id');
    }

    public function periodoLetivo(): BelongsTo
    {
        return $this->belongsTo(PeriodoLetivo::class);
    }

    public function avaliacoes(): HasMany
    {
        return $this->hasMany(Avaliacao::class);
    }

    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class);
    }

    public function cronogramasAula(): HasMany
    {
        return $this->hasMany(CronogramaAula::class);
    }
}
