<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatusAtestadoAluno;
use App\Services\AtestadoAlunoService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Atestado do aluno: o comprovante, o período de afastamento e se ele também abona as faltas (`abona_faltas`). Justificar
 * as faltas do período é sempre; abonar (não contar na frequência) é escolha registrada no atestado. As regras vivem em
 * {@see AtestadoAlunoService}; este model só guarda o estado.
 */
class AtestadoAluno extends Model
{
    use LogsActivity;

    protected $table = 'atestados_aluno';

    protected $fillable = [
        'matricula_id',
        'data_inicio',
        'data_fim',
        'arquivo_path',
        'observacao',
        'abona_faltas',
        'status',
        'motivo_cancelamento',
        'cancelado_em',
        'cancelado_por_user_id',
        'registrado_por_user_id',
    ];

    protected function casts(): array
    {
        return [
            'data_inicio' => 'date',
            'data_fim' => 'date',
            'observacao' => 'encrypted',
            'abona_faltas' => 'boolean',
            'status' => StatusAtestadoAluno::class,
            'cancelado_em' => 'datetime',
        ];
    }

    /**
     * O log registra o fluxo (quem, quando, período), nunca a observação nem o caminho do comprovante: o atestado traz
     * dado de saúde.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['matricula_id', 'data_inicio', 'data_fim', 'abona_faltas', 'status', 'registrado_por_user_id', 'cancelado_por_user_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('atestado_aluno')
            ->setDescriptionForEvent(function (string $eventName): string {
                $this->loadMissing('matricula.pessoa');

                $aluno = $this->matricula?->pessoa?->nome ?? 'matrícula '.$this->matricula_id;

                return match ($eventName) {
                    'created' => "Registrado atestado de {$aluno} ({$this->periodo()}).",
                    'updated' => "Atualizado atestado de {$aluno} ({$this->periodo()}).",
                    'deleted' => "Removido atestado de {$aluno} ({$this->periodo()}).",
                    default => "Atestado de {$aluno}: evento {$eventName}",
                };
            });
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }

    public function canceladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelado_por_user_id');
    }

    /**
     * Faltas que este atestado abonou.
     *
     * @return HasMany<FrequenciaEscolar, $this>
     */
    public function frequencias(): HasMany
    {
        return $this->hasMany(FrequenciaEscolar::class, 'atestado_aluno_id');
    }

    /**
     * @param  Builder<AtestadoAluno>  $query
     * @return Builder<AtestadoAluno>
     */
    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where('status', StatusAtestadoAluno::Vigente->value);
    }

    /**
     * Atestados cujo período inclui o dia. `whereDate` porque a coluna `date` pode vir como datetime em SQLite.
     *
     * @param  Builder<AtestadoAluno>  $query
     * @return Builder<AtestadoAluno>
     */
    public function scopeCobrindo(Builder $query, CarbonInterface $data): Builder
    {
        return $query->whereDate('data_inicio', '<=', $data->toDateString())
            ->whereDate('data_fim', '>=', $data->toDateString());
    }

    public function estaVigente(): bool
    {
        return $this->status === StatusAtestadoAluno::Vigente;
    }

    public function temComprovante(): bool
    {
        return filled($this->arquivo_path);
    }

    /**
     * "20/10/2026 a 22/10/2026" ou só "20/10/2026" quando é um dia.
     */
    public function periodo(): string
    {
        $inicio = $this->data_inicio?->format('d/m/Y');
        $fim = $this->data_fim?->format('d/m/Y');

        return $inicio === $fim ? (string) $inicio : "{$inicio} a {$fim}";
    }
}
