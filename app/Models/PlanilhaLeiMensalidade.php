<?php

namespace App\Models;

use App\Enums\StatusPlanilhaLei;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PlanilhaLeiMensalidade extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'planilha_lei_mensalidades';

    protected $fillable = [
        'ano_base',
        'ano_letivo_destino',
        'titulo',
        'unidade_id',
        'curso_id',
        'alunos_base',
        'mensalidade_media_base',
        'receita_anual_base',
        'custo_pessoal_base',
        'custo_custeio_base',
        'custo_investimento_base',
        'custo_total_base',
        'percentual_dissidio_pessoal',
        'variacao_pessoal_valor',
        'custo_pessoal_projetado',
        'percentual_inflacao_custeio',
        'variacao_custeio_valor',
        'custo_custeio_projetado',
        'valor_novos_investimentos',
        'custo_investimento_projetado',
        'custo_total_projetado',
        'meta_alunos_projetada',
        'variacao_custo_total_percentual',
        'percentual_reajuste_sugerido',
        'percentual_reajuste_adotado',
        'mensalidade_projetada',
        'anuidade_projetada',
        'status',
        'justificativa_pedagogica',
        'data_afixacao',
        'responsavel_user_id',
        'homologado_por_user_id',
        'homologado_em',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusPlanilhaLei::class,
            'ano_base' => 'integer',
            'ano_letivo_destino' => 'integer',
            'alunos_base' => 'integer',
            'meta_alunos_projetada' => 'integer',
            'mensalidade_media_base' => 'decimal:2',
            'receita_anual_base' => 'decimal:2',
            'custo_pessoal_base' => 'decimal:2',
            'custo_custeio_base' => 'decimal:2',
            'custo_investimento_base' => 'decimal:2',
            'custo_total_base' => 'decimal:2',
            'percentual_dissidio_pessoal' => 'decimal:2',
            'variacao_pessoal_valor' => 'decimal:2',
            'custo_pessoal_projetado' => 'decimal:2',
            'percentual_inflacao_custeio' => 'decimal:2',
            'variacao_custeio_valor' => 'decimal:2',
            'custo_custeio_projetado' => 'decimal:2',
            'valor_novos_investimentos' => 'decimal:2',
            'custo_investimento_projetado' => 'decimal:2',
            'custo_total_projetado' => 'decimal:2',
            'variacao_custo_total_percentual' => 'decimal:2',
            'percentual_reajuste_sugerido' => 'decimal:2',
            'percentual_reajuste_adotado' => 'decimal:2',
            'mensalidade_projetada' => 'decimal:2',
            'anuidade_projetada' => 'decimal:2',
            'data_afixacao' => 'date',
            'homologado_em' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'titulo', 'ano_base', 'ano_letivo_destino', 'percentual_reajuste_adotado',
                'mensalidade_projetada', 'status', 'homologado_em',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsavel_user_id');
    }

    public function homologador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'homologado_por_user_id');
    }

    public function isHomologadaOuPublicada(): bool
    {
        return in_array($this->status, [StatusPlanilhaLei::Homologada, StatusPlanilhaLei::Publicada], true);
    }
}
