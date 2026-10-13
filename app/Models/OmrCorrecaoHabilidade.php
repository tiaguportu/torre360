<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OmrCorrecaoHabilidadeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OmrCorrecaoHabilidade extends Model
{
    /** @use HasFactory<OmrCorrecaoHabilidadeFactory> */
    use HasFactory;

    protected $table = 'omr_correcao_habilidades';

    protected $fillable = [
        'omr_correcao_id',
        'habilidade_id',
        'acertos',
        'total',
        'percentual',
    ];

    protected function casts(): array
    {
        return [
            'acertos' => 'integer',
            'total' => 'integer',
            'percentual' => 'decimal:2',
        ];
    }

    public function correcao(): BelongsTo
    {
        return $this->belongsTo(OmrCorrecao::class, 'omr_correcao_id');
    }

    public function habilidade(): BelongsTo
    {
        return $this->belongsTo(Habilidade::class, 'habilidade_id');
    }
}
