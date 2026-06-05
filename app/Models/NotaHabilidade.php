<?php

namespace App\Models;

use App\Enums\ConceitoHabilidade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotaHabilidade extends Model
{
    protected $table = 'nota_habilidades';

    protected $fillable = [
        'avaliacao_habilidade_id',
        'matricula_id',
        'conceito',
        'observacao',
    ];

    public function avaliacaoHabilidade(): BelongsTo
    {
        return $this->belongsTo(AvaliacaoHabilidade::class, 'avaliacao_habilidade_id');
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    protected function casts(): array
    {
        return [
            'conceito' => ConceitoHabilidade::class,
        ];
    }
}
