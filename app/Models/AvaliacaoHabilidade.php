<?php

namespace App\Models;

use App\Enums\ConceitoHabilidade;
use Database\Factories\AvaliacaoHabilidadeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvaliacaoHabilidade extends Model
{
    /** @use HasFactory<AvaliacaoHabilidadeFactory> */
    use HasFactory;

    protected $table = 'avaliacao_habilidades';

    protected $fillable = [
        'matricula_id',
        'habilidade_id',
        'etapa_avaliativa_id',
        'conceito',
        'observacao',
    ];

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    public function habilidade(): BelongsTo
    {
        return $this->belongsTo(Habilidade::class);
    }

    public function etapaAvaliativa(): BelongsTo
    {
        return $this->belongsTo(EtapaAvaliativa::class);
    }

    protected function casts(): array
    {
        return [
            'conceito' => ConceitoHabilidade::class,
        ];
    }
}
