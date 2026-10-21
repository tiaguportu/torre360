<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatusAcaoDesenvolvimento;
use Database\Factories\AcaoDesenvolvimentoDocenteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ação de um plano de desenvolvimento: o que será feito, até quando e com que resultado. Descrição e resultado são
 * cifrados.
 */
class AcaoDesenvolvimentoDocente extends Model
{
    /** @use HasFactory<AcaoDesenvolvimentoDocenteFactory> */
    use HasFactory;

    protected $table = 'acoes_desenvolvimento_docente';

    protected $fillable = [
        'plano_id',
        'criterio_id',
        'descricao',
        'prazo',
        'status',
        'concluida_em',
        'resultado',
        'ordem',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'descricao' => 'encrypted',
            'resultado' => 'encrypted',
            'prazo' => 'date',
            'concluida_em' => 'date',
            'status' => StatusAcaoDesenvolvimento::class,
            'ordem' => 'integer',
        ];
    }

    public function plano(): BelongsTo
    {
        return $this->belongsTo(PlanoDesenvolvimentoDocente::class, 'plano_id');
    }

    public function criterio(): BelongsTo
    {
        return $this->belongsTo(CriterioAvaliacaoDocente::class, 'criterio_id');
    }

    public function estaAtrasada(): bool
    {
        return $this->status->emAberto() && $this->prazo->copy()->startOfDay()->lt(today());
    }
}
