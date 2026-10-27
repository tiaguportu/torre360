<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PeriodicidadeContaRecorrente;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Despesa fixa que se repete (aluguel, internet, folha de serviços): o modelo de onde a rotina gera as contas a pagar com
 * antecedência (Onda 78). O valor pode ser estimado e revisado na hora de pagar, e o reajuste do contrato tem data própria.
 */
class ContaRecorrente extends Model
{
    use LogsActivity;

    protected $table = 'contas_recorrentes';

    protected $fillable = [
        'descricao', 'fornecedor_id', 'plano_conta_id', 'centro_custo_id', 'periodicidade', 'dia_vencimento', 'valor', 'valor_estimado',
        'vigencia_inicio', 'vigencia_fim', 'antecedencia_dias', 'proximo_reajuste_em', 'observacao', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'periodicidade' => PeriodicidadeContaRecorrente::class,
            'dia_vencimento' => 'integer',
            'valor' => 'decimal:2',
            'valor_estimado' => 'boolean',
            'vigencia_inicio' => 'date',
            'vigencia_fim' => 'date',
            'antecedencia_dias' => 'integer',
            'proximo_reajuste_em' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['descricao', 'periodicidade', 'dia_vencimento', 'valor', 'valor_estimado', 'vigencia_inicio', 'vigencia_fim', 'proximo_reajuste_em', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('conta_recorrente');
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class);
    }

    public function planoConta(): BelongsTo
    {
        return $this->belongsTo(PlanoConta::class);
    }

    public function centroCusto(): BelongsTo
    {
        return $this->belongsTo(CentroCusto::class);
    }

    public function contas(): HasMany
    {
        return $this->hasMany(ContaPagar::class, 'conta_recorrente_id');
    }

    /**
     * @param  Builder<ContaRecorrente>  $query
     * @return Builder<ContaRecorrente>
     */
    public function scopeAtivas(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
