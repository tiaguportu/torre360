<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CicloAvaliacaoDocenteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Período em que a coordenação observa os professores (ex.: "1º Semestre 2026"), com o instrumento de avaliação
 * que vale para o ciclo.
 */
class CicloAvaliacaoDocente extends Model
{
    /** @use HasFactory<CicloAvaliacaoDocenteFactory> */
    use HasFactory;

    protected $table = 'ciclos_avaliacao_docente';

    protected $fillable = ['nome', 'data_inicio', 'data_fim', 'instrumento_id', 'ativo'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data_inicio' => 'date',
            'data_fim' => 'date',
            'ativo' => 'boolean',
        ];
    }

    public function instrumento(): BelongsTo
    {
        return $this->belongsTo(InstrumentoAvaliacaoDocente::class, 'instrumento_id');
    }

    public function avaliacoes(): HasMany
    {
        return $this->hasMany(AvaliacaoDocente::class, 'ciclo_id');
    }

    /**
     * Ciclos que aceitam novas observações hoje: ativos e com a data de hoje dentro do período.
     */
    public function scopeAbertos(Builder $query): Builder
    {
        return $query->where('ativo', true)
            ->whereDate('data_inicio', '<=', today()->toDateString())
            ->whereDate('data_fim', '>=', today()->toDateString());
    }

    public function aceitaObservacaoEm(\DateTimeInterface $data): bool
    {
        $dia = $data->format('Y-m-d');

        return $this->ativo
            && $dia >= $this->data_inicio->toDateString()
            && $dia <= $this->data_fim->toDateString();
    }
}
