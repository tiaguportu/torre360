<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ArredondamentoReajuste;
use App\Enums\StatusCenarioReajuste;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Simulação de reajuste de mensalidades de uma campanha de rematrícula: percentual, arredondamento e um item por série.
 * Aprovado e aplicado, grava o preço de cada série na campanha (`periodo_rematricula_precos`).
 */
class CenarioReajuste extends Model
{
    use LogsActivity;

    protected $table = 'cenarios_reajuste';

    protected $fillable = [
        'periodo_rematricula_id',
        'planilha_lei_id',
        'nome',
        'percentual_padrao',
        'arredondamento',
        'status',
        'observacao',
        'criado_por_user_id',
        'aprovado_por_user_id',
        'aprovado_em',
        'aplicado_em',
        'comunicado_em',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'percentual_padrao' => 'decimal:2',
            'arredondamento' => ArredondamentoReajuste::class,
            'status' => StatusCenarioReajuste::class,
            'aprovado_em' => 'datetime',
            'aplicado_em' => 'datetime',
            'comunicado_em' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['periodo_rematricula_id', 'nome', 'percentual_padrao', 'arredondamento', 'status', 'aprovado_por_user_id', 'aplicado_em', 'comunicado_em'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('reajuste_mensalidade')
            ->setDescriptionForEvent(fn (string $evento): string => match ($evento) {
                'created' => 'Cenário de reajuste criado.',
                'updated' => 'Cenário de reajuste atualizado.',
                'deleted' => 'Cenário de reajuste excluído.',
                default => "Cenário de reajuste: {$evento}",
            });
    }

    public function periodoRematricula(): BelongsTo
    {
        return $this->belongsTo(PeriodoRematricula::class);
    }

    public function planilhaLei(): BelongsTo
    {
        return $this->belongsTo(PlanilhaLeiMensalidade::class, 'planilha_lei_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(CenarioReajusteItem::class, 'cenario_id');
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por_user_id');
    }

    public function aprovadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprovado_por_user_id');
    }

    public function estaAplicado(): bool
    {
        return $this->status === StatusCenarioReajuste::Aplicado;
    }
}
