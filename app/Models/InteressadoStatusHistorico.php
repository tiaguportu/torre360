<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InteressadoStatusHistorico extends Model
{
    use HasFactory;

    protected $table = 'interessado_status_historico';

    protected $fillable = [
        'interessado_id',
        'status_anterior_id',
        'status_novo_id',
        'usuario_id',
        'motivo_perda',
        'data_transicao',
        'estimada',
    ];

    protected function casts(): array
    {
        return [
            'data_transicao' => 'datetime',
            'estimada' => 'boolean',
        ];
    }

    public function interessado(): BelongsTo
    {
        return $this->belongsTo(Interessado::class, 'interessado_id');
    }

    public function statusAnterior(): BelongsTo
    {
        return $this->belongsTo(StatusInteressado::class, 'status_anterior_id');
    }

    public function statusNovo(): BelongsTo
    {
        return $this->belongsTo(StatusInteressado::class, 'status_novo_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function scopeReais(Builder $query): Builder
    {
        return $query->where('estimada', false);
    }

    public function scopeNoPeriodo(Builder $query, Carbon|string $inicio, Carbon|string $fim): Builder
    {
        return $query->whereBetween('data_transicao', [$inicio, $fim]);
    }
}
