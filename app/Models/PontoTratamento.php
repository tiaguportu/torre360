<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatusTratamentoPonto;
use App\Enums\TipoTratamentoPonto;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Ajuste sobre a jornada de um dia (marcação esquecida, marcação indevida ou abono). Nunca altera o registro original:
 * o ajuste vive ao lado dele e só conta na apuração depois de aprovado.
 */
class PontoTratamento extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'ponto_tratamentos';

    protected $fillable = [
        'unidade_id',
        'funcionario_id',
        'data',
        'tipo',
        'momento',
        'registro_id',
        'minutos_abono',
        'motivo',
        'status',
        'solicitado_por_user_id',
        'decidido_por_user_id',
        'decidido_em',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'date',
            'tipo' => TipoTratamentoPonto::class,
            'momento' => 'datetime',
            'minutos_abono' => 'integer',
            'motivo' => 'encrypted',
            'status' => StatusTratamentoPonto::class,
            'decidido_em' => 'datetime',
        ];
    }

    /**
     * O log registra o fluxo, nunca o motivo (que pode citar atestado médico).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['funcionario_id', 'data', 'tipo', 'status', 'decidido_por_user_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('ponto')
            ->setDescriptionForEvent(fn (string $evento): string => "Tratamento de ponto: {$evento}");
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class);
    }

    public function registro(): BelongsTo
    {
        return $this->belongsTo(PontoRegistro::class, 'registro_id');
    }

    public function solicitadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_por_user_id');
    }

    public function decididoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decidido_por_user_id');
    }

    public function scopeAprovados($query)
    {
        return $query->where('status', StatusTratamentoPonto::Aprovado->value);
    }
}
