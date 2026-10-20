<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nota dada a um critério do instrumento numa avaliação docente. A observação é cifrada, como o parecer.
 */
class AvaliacaoDocenteCriterio extends Model
{
    protected $table = 'avaliacao_docente_criterios';

    protected $fillable = ['avaliacao_docente_id', 'criterio_id', 'nota', 'observacao'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nota' => 'integer',
            'observacao' => 'encrypted',
        ];
    }

    public function avaliacao(): BelongsTo
    {
        return $this->belongsTo(AvaliacaoDocente::class, 'avaliacao_docente_id');
    }

    public function criterio(): BelongsTo
    {
        return $this->belongsTo(CriterioAvaliacaoDocente::class, 'criterio_id');
    }
}
