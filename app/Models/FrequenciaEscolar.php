<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class FrequenciaEscolar extends Model
{
    use LogsActivity;

    protected $table = 'frequencia_escolar';

    protected $guarded = [];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['matricula_id', 'cronograma_aula_id', 'situacao'])
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
}
