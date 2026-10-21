<?php

namespace App\Models;

use App\Enums\StatusPeriodoFerias;
use Carbon\CarbonInterface;
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

    /**
     * Último dia para o empregador conceder as férias: 12 meses depois do fim do período aquisitivo (CLT, art. 134).
     */
    public function limiteDeConcessao(): CarbonInterface
    {
        return $this->periodo_aquisitivo_fim->copy()->startOfDay()->addYearNoOverflow();
    }
}
