<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Validation\ValidationException;

class Avaliacao extends Model
{
    protected $table = 'avaliacao';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data_prevista' => 'date',
            'data_ocorrencia' => 'date',
            'data_limite_lancamento' => 'date',
            'nota_maxima' => 'decimal:2',
            'peso_etapa_avaliativa' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Avaliacao $avaliacao) {
            $query = static::where('turma_id', $avaliacao->turma_id)
                ->where('disciplina_id', $avaliacao->disciplina_id)
                ->where('etapa_avaliativa_id', $avaliacao->etapa_avaliativa_id)
                ->where('categoria_avaliacao_id', $avaliacao->categoria_avaliacao_id);

            if (is_null($avaliacao->professor_id)) {
                $query->whereNull('professor_id');
            } else {
                $query->where('professor_id', $avaliacao->professor_id);
            }

            if ($avaliacao->exists) {
                $query->where('id', '!=', $avaliacao->id);
            }

            if ($query->exists()) {
                throw ValidationException::withMessages([
                    'turma_id' => 'Já existe uma avaliação cadastrada com esta mesma combinação de Turma, Disciplina, Etapa Avaliativa, Categoria e Professor.',
                ]);
            }
        });
    }

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

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaAvaliacao::class, 'categoria_avaliacao_id');
    }

    public function matriculas(): HasManyThrough
    {
        return $this->hasManyThrough(Matricula::class, Turma::class, 'id', 'turma_id', 'turma_id', 'id');
    }

    public function getLabelExibicaoAttribute(): string
    {
        return sprintf(
            '%s - %s - %s - %s',
            $this->categoria?->nome ?? 'Sem Categoria',
            $this->turma?->nome ?? 'Sem Turma',
            $this->disciplina?->nome ?? 'Sem Disciplina',
            $this->etapaAvaliativa?->nome ?? 'Sem Etapa'
        );
    }

    /**
     * Escopo para filtrar avaliações onde faltam notas de alunos ativos.
     */
    public function scopePendentes(Builder $query): Builder
    {
        return $query->whereHas('turma.matriculas', function ($q) {
            $q->where('situacao', 'ativa');
        }, '>', function ($query) {
            $query->selectRaw('count(*)')
                ->from('nota')
                ->whereColumn('avaliacao_id', 'avaliacao.id')
                ->whereNotNull('valor');
        });
    }

    /**
     * Verifica se há pendência de lançamento para esta avaliação.
     */
    public function getTemPendenciaAttribute(): bool
    {
        $totalAlunos = $this->turma?->matriculas()->where('situacao', 'ativa')->count() ?? 0;
        $totalNotas = $this->notas()->whereNotNull('valor')->count();

        return $totalNotas < $totalAlunos;
    }

    /**
     * Retorna a quantidade de notas pendentes.
     */
    public function getNotasPendentesCountAttribute(): int
    {
        $totalAlunos = $this->turma?->matriculas()->where('situacao', 'ativa')->count() ?? 0;
        $totalNotas = $this->notas()->whereNotNull('valor')->count();

        return max(0, $totalAlunos - $totalNotas);
    }
}
