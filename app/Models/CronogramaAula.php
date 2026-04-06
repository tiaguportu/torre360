<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CronogramaAula extends Model
{
    protected $table = 'cronograma_aula';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data' => 'date',
        ];
    }

    public function frequencias(): HasMany
    {
        return $this->hasMany(FrequenciaEscolar::class);
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class);
    }

    public function professor(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'pessoa_id');
    }

    public function periodoLetivo(): BelongsTo
    {
        return $this->belongsTo(PeriodoLetivo::class);
    }

    public function hasPendingFrequencies(): bool
    {
        $totalMatriculados = $this->turma->matriculas()->count();
        $frequenciasLancadas = $this->frequencias()->whereNotNull('situacao')->count();

        return ($totalMatriculados - $frequenciasLancadas) > 0;
    }
}
