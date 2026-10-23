<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ReposicaoAula extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'reposicao_aulas';

    protected $fillable = [
        'turma_id',
        'disciplina_id',
        'pessoa_id',
        'cronograma_aula_original_id',
        'cronograma_aula_reposicao_id',
        'data_reposicao',
        'hora_inicio',
        'hora_fim',
        'motivo',
        'status',
        'observacoes',
        'criado_por_user_id',
    ];

    protected function casts(): array
    {
        return [
            'data_reposicao' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'turma_id', 'disciplina_id', 'pessoa_id', 'data_reposicao',
                'hora_inicio', 'hora_fim', 'motivo', 'status',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('reposicao_aula');
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function disciplina(): BelongsTo
    {
        return $this->belongsTo(Disciplina::class);
    }

    public function professor(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'pessoa_id');
    }

    public function aulaOriginal(): BelongsTo
    {
        return $this->belongsTo(CronogramaAula::class, 'cronograma_aula_original_id');
    }

    public function aulaReposicao(): BelongsTo
    {
        return $this->belongsTo(CronogramaAula::class, 'cronograma_aula_reposicao_id');
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por_user_id');
    }

    /**
     * Duração em minutos da reposição.
     */
    public function duracaoMinutos(): int
    {
        if (! $this->hora_inicio || ! $this->hora_fim) {
            return 50; // fallback padrão de uma hora-aula
        }

        $inicio = Carbon::parse($this->hora_inicio);
        $fim = Carbon::parse($this->hora_fim);

        return abs($fim->diffInMinutes($inicio));
    }
}
