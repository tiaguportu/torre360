<?php

namespace App\Models;

use App\Enums\SituacaoFinal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SituacaoFinalDisciplina extends Model
{
    protected $table = 'situacao_final_disciplina';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'media_final' => 'decimal:2',
            'situacao' => SituacaoFinal::class,
            'calculado_em' => 'datetime',
        ];
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class);
    }

    public function periodoLetivo(): BelongsTo
    {
        return $this->belongsTo(PeriodoLetivo::class);
    }
}
