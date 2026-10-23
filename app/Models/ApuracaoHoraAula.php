<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ApuracaoHoraAula extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'apuracao_hora_aulas';

    protected $fillable = [
        'pessoa_id',
        'ano',
        'mes',
        'total_aulas_previstas',
        'total_aulas_ministradas',
        'total_aulas_reposicao',
        'total_aulas_faltadas',
        'total_horas_atividade',
        'valor_hora_aula',
        'percentual_dsr',
        'valor_total_bruto',
        'status',
        'conferido_em',
        'observacoes',
        'criado_por_user_id',
    ];

    protected function casts(): array
    {
        return [
            'ano' => 'integer',
            'mes' => 'integer',
            'total_aulas_previstas' => 'integer',
            'total_aulas_ministradas' => 'integer',
            'total_aulas_reposicao' => 'integer',
            'total_aulas_faltadas' => 'integer',
            'total_horas_atividade' => 'decimal:2',
            'valor_hora_aula' => 'decimal:2',
            'percentual_dsr' => 'decimal:2',
            'valor_total_bruto' => 'decimal:2',
            'conferido_em' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'pessoa_id', 'ano', 'mes', 'total_aulas_ministradas',
                'valor_hora_aula', 'valor_total_bruto', 'status',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('apuracao_hora_aula');
    }

    public function professor(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'pessoa_id');
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por_user_id');
    }

    /**
     * Total de aulas remuneráveis (ministradas + reposições).
     */
    public function totalAulasRemuneraveis(): int
    {
        return (int) $this->total_aulas_ministradas + (int) $this->total_aulas_reposicao;
    }

    /**
     * Retorna a competência no formato MM/AAAA.
     */
    public function getCompetenciaAttribute(): string
    {
        return sprintf('%02d/%d', $this->mes, $this->ano);
    }
}
