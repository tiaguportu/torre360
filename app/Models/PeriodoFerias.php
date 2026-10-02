<?php

namespace App\Models;

use App\Enums\StatusPeriodoFerias;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeriodoFerias extends Model
{
    use HasFactory;

    protected $table = 'periodos_ferias';

    protected $fillable = [
        'funcionario_id',
        'periodo_aquisitivo_inicio',
        'periodo_aquisitivo_fim',
        'dias_direito',
        'dias_gozados',
        'data_inicio_gozo',
        'data_fim_gozo',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'periodo_aquisitivo_inicio' => 'date',
            'periodo_aquisitivo_fim' => 'date',
            'data_inicio_gozo' => 'date',
            'data_fim_gozo' => 'date',
            'status' => StatusPeriodoFerias::class,
        ];
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class);
    }

    public function getDiasSaldoAttribute(): int
    {
        return $this->dias_direito - $this->dias_gozados;
    }
}
