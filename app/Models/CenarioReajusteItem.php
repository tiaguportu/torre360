<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma série dentro de um cenário de reajuste: valor atual (média dos contratos do período de origem, editável),
 * percentual próprio (opcional) e valor novo.
 */
class CenarioReajusteItem extends Model
{
    protected $table = 'cenario_reajuste_itens';

    protected $fillable = [
        'cenario_id',
        'serie_id',
        'percentual',
        'valor_atual',
        'valor_novo',
        'alunos',
        'bolsa_media',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'percentual' => 'decimal:2',
            'valor_atual' => 'decimal:2',
            'valor_novo' => 'decimal:2',
            'alunos' => 'integer',
            'bolsa_media' => 'decimal:2',
        ];
    }

    public function cenario(): BelongsTo
    {
        return $this->belongsTo(CenarioReajuste::class, 'cenario_id');
    }

    public function serie(): BelongsTo
    {
        return $this->belongsTo(Serie::class);
    }

    /** Percentual efetivo: o do item, ou o padrão do cenário. */
    public function percentualEfetivo(): float
    {
        return (float) ($this->percentual ?? $this->cenario->percentual_padrao);
    }

    /** Variação do valor por aluno, em reais. */
    public function variacaoPorAluno(): float
    {
        return round((float) $this->valor_novo - (float) $this->valor_atual, 2);
    }
}
