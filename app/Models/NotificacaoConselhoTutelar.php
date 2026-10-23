<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class NotificacaoConselhoTutelar extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'notificacoes_conselho_tutelar';

    protected $fillable = [
        'matricula_id',
        'turma_id',
        'ano_letivo',
        'numero_oficio',
        'total_aulas_previstas',
        'total_presencas',
        'total_faltas',
        'total_faltas_justificadas',
        'total_faltas_injustificadas',
        'percentual_faltas',
        'tentativas_contato',
        'status',
        'data_notificacao',
        'protocolo_conselho',
        'nome_conselho_tutelar',
        'municipio_conselho',
        'observacoes',
        'criado_por_user_id',
    ];

    protected function casts(): array
    {
        return [
            'ano_letivo' => 'integer',
            'total_aulas_previstas' => 'integer',
            'total_presencas' => 'integer',
            'total_faltas' => 'integer',
            'total_faltas_justificadas' => 'integer',
            'total_faltas_injustificadas' => 'integer',
            'percentual_faltas' => 'decimal:2',
            'tentativas_contato' => 'array',
            'data_notificacao' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'matricula_id', 'numero_oficio', 'status', 'total_faltas',
                'percentual_faltas', 'data_notificacao', 'protocolo_conselho',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('notificacao_conselho_tutelar');
    }

    public static function gerarNumeroOficio(): string
    {
        $ano = now()->year;
        $ultimoId = (int) static::where('ano_letivo', $ano)->max('id') + 1;

        return sprintf('FICAI-%d-%05d', $ano, $ultimoId);
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por_user_id');
    }

    /**
     * Verifica se o aluno ultrapassou o limite legal da LDB (30% do limite permitido = 7.5% das aulas do ano).
     */
    public function atingiuLimiteLegal(): bool
    {
        return (float) $this->percentual_faltas >= 7.5;
    }

    /**
     * Verifica se foram esgotados os recursos escolares (ECA art. 56, II).
     * Requer ao menos 2 tentativas documentadas de contato com a família.
     */
    public function temTentativasSuficientes(): bool
    {
        $tentativas = $this->tentativas_contato ?? [];

        return count($tentativas) >= 2;
    }
}
