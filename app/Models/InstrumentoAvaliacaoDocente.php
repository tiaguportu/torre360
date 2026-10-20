<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\InstrumentoAvaliacaoDocenteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Roteiro de observação em sala: a lista de critérios pedagógicos (e a escala de notas) que a coordenação usa para
 * avaliar um professor. É configurável pela escola e reutilizado por vários ciclos.
 */
class InstrumentoAvaliacaoDocente extends Model
{
    /** @use HasFactory<InstrumentoAvaliacaoDocenteFactory> */
    use HasFactory;

    protected $table = 'instrumentos_avaliacao_docente';

    protected $fillable = ['nome', 'descricao', 'escala_maxima', 'ativo'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'escala_maxima' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    public function criterios(): HasMany
    {
        return $this->hasMany(CriterioAvaliacaoDocente::class, 'instrumento_id')->orderBy('ordem')->orderBy('id');
    }

    public function ciclos(): HasMany
    {
        return $this->hasMany(CicloAvaliacaoDocente::class, 'instrumento_id');
    }

    /**
     * Já há avaliação feita com este instrumento? Então os critérios ficam congelados: mudar peso ou apagar
     * critério reescreveria o histórico dos professores já avaliados.
     */
    public function emUso(): bool
    {
        return AvaliacaoDocente::query()
            ->whereHas('ciclo', fn ($q) => $q->where('instrumento_id', $this->id))
            ->exists();
    }
}
