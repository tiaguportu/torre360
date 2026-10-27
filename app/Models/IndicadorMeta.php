<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Meta anual de um indicador do Painel da Direção (Onda 45), da escola toda (`unidade_id` 0) ou de uma unidade. A tolerância é a
 * faixa amarela do semáforo.
 */
class IndicadorMeta extends Model
{
    use LogsActivity;

    protected $table = 'indicador_metas';

    protected $fillable = ['indicador', 'ano', 'unidade_id', 'meta', 'tolerancia_percentual', 'observacao'];

    protected function casts(): array
    {
        return [
            'ano' => 'integer',
            'unidade_id' => 'integer',
            'meta' => 'decimal:2',
            'tolerancia_percentual' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['indicador', 'ano', 'unidade_id', 'meta', 'tolerancia_percentual'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('indicador_meta');
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class, 'unidade_id');
    }
}
