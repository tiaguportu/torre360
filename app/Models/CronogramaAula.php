<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CronogramaAula extends Model
{
    use HasFactory;

    protected $table = 'cronograma_aula';

    protected $fillable = ['turma_id', 'disciplina_id', 'pessoa_id', 'data', 'hora_inicio', 'hora_fim', 'conteudo_ministrado', 'dever_casa', 'anexo_material', 'nao_realizada_em', 'motivo_nao_realizacao', 'reposicao_de_id'];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'anexo_material' => 'array',
            'nao_realizada_em' => 'datetime',
        ];
    }

    /**
     * A aula que esta repõe (Onda 49).
     */
    public function reposicaoDe(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reposicao_de_id');
    }

    /**
     * As aulas de reposição desta aula, quando ela não aconteceu.
     */
    public function reposicoes(): HasMany
    {
        return $this->hasMany(self::class, 'reposicao_de_id');
    }

    public function naoFoiRealizada(): bool
    {
        return $this->nao_realizada_em !== null;
    }

    public function ehReposicao(): bool
    {
        return $this->reposicao_de_id !== null;
    }

    public function habilidades()
    {
        return $this->belongsToMany(Habilidade::class, 'cronograma_aula_habilidade', 'cronograma_aula_id', 'habilidade_id');
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
        $totalMatriculados = $this->turma->matriculas()
            ->where(function ($q) {
                $q->whereNull('data_ativacao')
                    ->orWhereDate('data_ativacao', '<=', $this->data);
            })
            ->where(function ($q) {
                $q->whereNull('data_desativacao')
                    ->orWhereDate('data_desativacao', '>', $this->data);
            })
            ->count();

        $frequenciasLancadas = $this->frequencias()->whereNotNull('situacao')->count();

        return ($totalMatriculados - $frequenciasLancadas) > 0;
    }
}
