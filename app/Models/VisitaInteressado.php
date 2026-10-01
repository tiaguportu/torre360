<?php

namespace App\Models;

use App\Enums\StatusVisitaInteressado;
use Database\Factories\VisitaInteressadoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitaInteressado extends Model
{
    /** @use HasFactory<VisitaInteressadoFactory> */
    use HasFactory;

    protected $table = 'visita_interessado';

    protected $fillable = ['interessado_id', 'interessado_dependente_id', 'usuario_id', 'data_hora', 'status', 'observacoes', 'lembrete_enviado_em'];

    protected function casts(): array
    {
        return [
            'data_hora' => 'datetime',
            'lembrete_enviado_em' => 'datetime',
            'status' => StatusVisitaInteressado::class,
        ];
    }

    public function interessado(): BelongsTo
    {
        return $this->belongsTo(Interessado::class);
    }

    public function dependente(): BelongsTo
    {
        return $this->belongsTo(InteressadoDependente::class, 'interessado_dependente_id');
    }

    /**
     * Consultor responsável pela visita.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function scopeAgendadas(Builder $query): Builder
    {
        return $query->where('status', StatusVisitaInteressado::Agendada);
    }

    /**
     * Visitas agendadas que ocorrem dentro da janela informada e ainda sem lembrete enviado.
     */
    public function scopePendentesDeLembrete(Builder $query, int $horas = 24): Builder
    {
        return $query->agendadas()
            ->whereNull('lembrete_enviado_em')
            ->whereBetween('data_hora', [now(), now()->addHours($horas)]);
    }

    public function estaAtrasada(): bool
    {
        return $this->status === StatusVisitaInteressado::Agendada && $this->data_hora->isPast();
    }
}
