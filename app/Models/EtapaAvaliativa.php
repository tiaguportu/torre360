<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class EtapaAvaliativa extends Model
{
    protected $table = 'etapa_avaliativa';

    protected $fillable = ['turma_id', 'nome', 'data_inicio', 'data_fim', 'periodo_letivo_id'];

    /**
     * As etapas de uma turma. A coluna `turma_id` só existe onde o esquema vem do SQLite (testes): em MySQL a migration
     * `refine_educational_relationships` a remove, e a etapa é do período letivo, igual para todas as turmas dele.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeDaTurma(Builder $query, Turma $turma): Builder
    {
        return Schema::hasColumn($this->getTable(), 'turma_id')
            ? $query->where('turma_id', $turma->id)
            : $query->where('periodo_letivo_id', $turma->periodo_letivo_id);
    }

    public function periodoLetivo(): BelongsTo
    {
        return $this->belongsTo(PeriodoLetivo::class);
    }

    public function avaliacoes(): HasMany
    {
        return $this->hasMany(Avaliacao::class, 'etapa_avaliativa_id');
    }
}
