<?php

namespace App\Models;

use App\Observers\FrequenciaFaltaObserver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[ObservedBy(FrequenciaFaltaObserver::class)]
class FrequenciaEscolar extends Model
{
    use LogsActivity;

    protected $table = 'frequencia_escolar';

    protected $fillable = [
        'matricula_id',
        'cronograma_aula_id',
        'situacao',
        'justificada',
        'abonada',
        'motivo_justificativa',
        'atestado_aluno_id',
    ];

    protected function casts(): array
    {
        return [
            'justificada' => 'boolean',
            'abonada' => 'boolean',
            'aviso_resumo_pendente' => 'boolean',
            'aviso_resumo_enviado_em' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['matricula_id', 'cronograma_aula_id', 'situacao', 'justificada', 'abonada', 'motivo_justificativa'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('frequencia_escolar')
            ->setDescriptionForEvent(function (string $eventName) {
                $alunoNome = $this->matricula?->pessoa?->nome ?? 'Matrícula '.$this->matricula_id;

                $ca = $this->cronogramaAula;
                $aulaInfo = 'Aula não identificada';
                if ($ca) {
                    $data = Carbon::parse($ca->data)->format('d/m/Y');
                    $turma = $ca->turma?->nome ?? 'N/A';
                    $disciplina = $ca->disciplina?->nome ?? 'N/A';
                    $aulaInfo = "{$disciplina} da turma {$turma} em {$data}";
                }

                $situacaoLabel = match ($this->situacao) {
                    'presente' => 'Presente',
                    'ausente' => 'Ausente',
                    default => $this->situacao ?? 'Sem registro',
                };

                return match ($eventName) {
                    'created' => "Registrada frequência do aluno {$alunoNome} como '{$situacaoLabel}' na {$aulaInfo}.",
                    'updated' => "Atualizada frequência do aluno {$alunoNome} para '{$situacaoLabel}' na {$aulaInfo}.",
                    'deleted' => "Removido registro de frequência do aluno {$alunoNome} na {$aulaInfo}.",
                    default => "Frequência do aluno {$alunoNome}: evento {$eventName}",
                };
            });
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    public function cronogramaAula(): BelongsTo
    {
        return $this->belongsTo(CronogramaAula::class);
    }

    /**
     * Faltas que contam contra a frequência: ausente e não abonada. A falta justificada continua contando; só o abono a
     * tira da conta (abonar exige justificar, mas justificar não abona).
     *
     * @param  Builder<FrequenciaEscolar>  $query
     * @return Builder<FrequenciaEscolar>
     */
    public function scopeContaComoFalta(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('situacao'), 'ausente')->where($this->qualifyColumn('abonada'), false);
    }

    /**
     * A aula entra na frequência como presença: presente, ou falta abonada.
     */
    public function contaComoPresenca(): bool
    {
        return $this->situacao === 'presente' || ($this->situacao === 'ausente' && $this->abonada);
    }

    /**
     * Atestado que justificou esta falta (nulo na falta justificada à mão ou sem justificativa).
     */
    public function atestadoAluno(): BelongsTo
    {
        return $this->belongsTo(AtestadoAluno::class);
    }
}
