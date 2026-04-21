<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Preceptoria extends Model
{
    use HasFactory;

    protected $table = 'preceptoria';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data' => 'date',
        ];
    }

    public function professor(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'professor_id');
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    public function relatorio(): HasOne
    {
        return $this->hasOne(RelatorioPreceptoria::class);
    }

    /**
     * Label de exibição amigável.
     */
    public function getLabelExibicaoAttribute(): string
    {
        return sprintf(
            '%s %s – %s',
            $this->data?->format('d/m/Y') ?? '',
            $this->hora_inicio ?? '',
            $this->professor?->nome ?? 'S/P'
        );
    }
}
