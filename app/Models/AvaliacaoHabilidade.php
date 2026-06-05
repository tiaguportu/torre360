<?php

namespace App\Models;

use Database\Factories\AvaliacaoHabilidadeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AvaliacaoHabilidade extends Model
{
    /** @use HasFactory<AvaliacaoHabilidadeFactory> */
    use HasFactory;

    protected $table = 'avaliacao_habilidades';

    protected $fillable = [
        'turma_id',
        'etapa_avaliativa_id',
        'professor_id',
    ];

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function etapaAvaliativa(): BelongsTo
    {
        return $this->belongsTo(EtapaAvaliativa::class, 'etapa_avaliativa_id');
    }

    public function professor(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'professor_id');
    }

    public function notas(): HasMany
    {
        return $this->hasMany(NotaHabilidade::class, 'avaliacao_habilidade_id');
    }
}
