<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Preceptoria extends Model
{
    use HasFactory;

    protected $table = 'preceptoria';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'hora_inicio' => 'datetime',
            'hora_fim' => 'datetime',
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

    public function relatorios(): HasMany
    {
        return $this->hasMany(RelatorioPreceptoria::class);
    }

    /**
     * Label de exibição amigável.
     */
    public function getLabelExibicaoAttribute(): string
    {
        $data = $this->data ? Carbon::parse($this->data)->format('d/m/Y') : '';
        $inicio = $this->hora_inicio ? Carbon::parse($this->hora_inicio)->format('H:i') : '';

        return sprintf(
            '%s %s – %s',
            $data,
            $inicio,
            $this->professor?->nome ?? 'S/P'
        );
    }
}
