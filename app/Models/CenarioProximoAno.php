<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cenário salvo do simulador do próximo ano letivo (Onda 76): o que foi suposto (parâmetros) e o que a projeção deu naquele dia
 * (resultado). Projeção não é compromisso: o cenário guarda a fotografia para a reunião, não acompanha os dados depois.
 */
class CenarioProximoAno extends Model
{
    protected $table = 'cenarios_proximo_ano';

    protected $fillable = ['nome', 'periodo_rematricula_id', 'parametros', 'resultado', 'criado_por_user_id'];

    protected function casts(): array
    {
        return [
            'parametros' => 'array',
            'resultado' => 'array',
        ];
    }

    public function periodoRematricula(): BelongsTo
    {
        return $this->belongsTo(PeriodoRematricula::class);
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por_user_id');
    }
}
