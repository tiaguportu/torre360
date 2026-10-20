<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatusReservaEspaco;
use Carbon\Carbon;
use Database\Factories\ReservaEspacoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Uso pontual de uma `Sala` numa data e faixa de horário (ensaio, palestra, aula extra), fora da grade horária
 * semanal fixa (`GradeHorario`). A disponibilidade é validada por `ReservaEspacoConflitoService`.
 */
class ReservaEspaco extends Model
{
    /** @use HasFactory<ReservaEspacoFactory> */
    use HasFactory;

    use LogsActivity;

    protected $table = 'reservas_espaco';

    protected $fillable = [
        'sala_id',
        'data',
        'hora_inicio',
        'hora_fim',
        'solicitante_user_id',
        'motivo',
        'descricao',
        'turma_id',
        'status',
        'decidido_por_user_id',
        'decidido_em',
        'justificativa_decisao',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'date',
            'status' => StatusReservaEspaco::class,
            'decidido_em' => 'datetime',
        ];
    }

    /**
     * Horários são sempre gravados como `HH:MM:SS`, qualquer que seja a origem (formulário, serviço, importação):
     * a checagem de sobreposição compara os valores como texto em alguns bancos, e `14:00` ordena antes de `14:00:00`.
     */
    protected function horaInicio(): Attribute
    {
        return Attribute::make(set: fn (?string $valor): ?string => $valor === null ? null : Carbon::parse($valor)->format('H:i:s'));
    }

    protected function horaFim(): Attribute
    {
        return Attribute::make(set: fn (?string $valor): ?string => $valor === null ? null : Carbon::parse($valor)->format('H:i:s'));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['sala_id', 'data', 'hora_inicio', 'hora_fim', 'motivo', 'turma_id', 'status', 'justificativa_decisao'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('reserva_espaco')
            ->setDescriptionForEvent(fn (string $evento): string => match ($evento) {
                'created' => 'Reserva de espaço registrada.',
                'updated' => 'Reserva de espaço atualizada.',
                'deleted' => 'Reserva de espaço excluída.',
                default => "Reserva de espaço: {$evento}",
            });
    }

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class);
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitante_user_id');
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function decididoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decidido_por_user_id');
    }

    /**
     * Reservas que ocupam o espaço (solicitadas ou confirmadas).
     */
    public function scopeQueOcupam(Builder $query): Builder
    {
        return $query->whereIn('status', [StatusReservaEspaco::Solicitada->value, StatusReservaEspaco::Confirmada->value]);
    }

    /**
     * Reservas da sala cujo intervalo cruza `[inicio, fim)` na data. Faixas que apenas se tocam (uma termina
     * quando a outra começa) não se sobrepõem.
     */
    public function scopeSobrepoe(Builder $query, int $salaId, string $data, string $horaInicio, string $horaFim): Builder
    {
        return $query->where('sala_id', $salaId)
            ->whereDate('data', $data)
            ->where('hora_inicio', '<', $horaFim)
            ->where('hora_fim', '>', $horaInicio);
    }

    public function estaCancelada(): bool
    {
        return $this->status === StatusReservaEspaco::Cancelada;
    }

    public function aguardaAprovacao(): bool
    {
        return $this->status === StatusReservaEspaco::Solicitada;
    }

    /**
     * A reserva ainda não terminou (data futura ou hoje com horário final adiante).
     */
    public function aindaNaoOcorreu(): bool
    {
        $fim = $this->data->copy()->setTimeFromTimeString((string) $this->hora_fim);

        return $fim->isFuture();
    }

    public function intervaloFormatado(): string
    {
        return substr((string) $this->hora_inicio, 0, 5).' às '.substr((string) $this->hora_fim, 0, 5);
    }
}
