<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nota que o professor deu a si mesmo em um critério do instrumento. A observação é cifrada.
 */
class AutoavaliacaoNota extends Model
{
    protected $table = 'autoavaliacao_notas';

    protected $fillable = ['autoavaliacao_id', 'criterio_id', 'nota', 'observacao'];

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

    public function autoavaliacao(): BelongsTo
    {
        return $this->belongsTo(AutoavaliacaoDocente::class, 'autoavaliacao_id');
    }

    public function criterio(): BelongsTo
    {
        return $this->belongsTo(CriterioAvaliacaoDocente::class, 'criterio_id');
    }
}
