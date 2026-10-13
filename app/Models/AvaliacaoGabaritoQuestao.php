<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AvaliacaoGabaritoQuestaoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvaliacaoGabaritoQuestao extends Model
{
    /** @use HasFactory<AvaliacaoGabaritoQuestaoFactory> */
    use HasFactory;

    protected $table = 'avaliacao_gabarito_questoes';

    protected $fillable = [
        'avaliacao_gabarito_id',
        'numero',
        'alternativa_correta',
        'peso',
        'habilidade_id',
        'anulada',
    ];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'alternativa_correta' => 'string',
            'peso' => 'decimal:2',
            'anulada' => 'boolean',
        ];
    }

    public function gabarito(): BelongsTo
    {
        return $this->belongsTo(AvaliacaoGabarito::class, 'avaliacao_gabarito_id');
    }

    public function habilidade(): BelongsTo
    {
        return $this->belongsTo(Habilidade::class, 'habilidade_id');
    }
}
