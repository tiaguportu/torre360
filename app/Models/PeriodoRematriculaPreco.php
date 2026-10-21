<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Preço do novo contrato de uma série numa campanha de rematrícula. A efetivação da rematrícula usa este valor; sem
 * linha para a série, vale o `valor_taxa` da campanha.
 */
class PeriodoRematriculaPreco extends Model
{
    protected $table = 'periodo_rematricula_precos';

    protected $fillable = ['periodo_rematricula_id', 'serie_id', 'valor_total', 'cenario_reajuste_item_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['valor_total' => 'decimal:2'];
    }

    public function periodoRematricula(): BelongsTo
    {
        return $this->belongsTo(PeriodoRematricula::class);
    }

    public function serie(): BelongsTo
    {
        return $this->belongsTo(Serie::class);
    }
}
