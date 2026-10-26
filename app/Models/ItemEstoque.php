<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\EstoqueService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Item de estoque de consumo (Onda 16, fase 2). O saldo e o custo médio ficam na linha; quem muda os dois é sempre
 * {@see EstoqueService}, que também grava a movimentação.
 */
class ItemEstoque extends Model
{
    protected $table = 'itens_estoque';

    protected $fillable = ['codigo', 'nome', 'categoria', 'unidade_medida', 'unidade_id', 'estoque_minimo', 'quantidade_atual', 'custo_medio', 'plano_conta_id', 'is_active'];

    /** @var array<string, string> sigla => rótulo */
    public const UNIDADES_DE_MEDIDA = [
        'un' => 'Unidade',
        'cx' => 'Caixa',
        'pct' => 'Pacote',
        'rs' => 'Resma',
        'l' => 'Litro',
        'kg' => 'Quilo',
        'm' => 'Metro',
    ];

    protected function casts(): array
    {
        return [
            'estoque_minimo' => 'decimal:3',
            'quantidade_atual' => 'decimal:3',
            'custo_medio' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function movimentacoes(): HasMany
    {
        return $this->hasMany(MovimentacaoEstoque::class, 'item_estoque_id')->orderByDesc('id');
    }

    public function unidade(): BelongsTo
    {
        return $this->belongsTo(Unidade::class);
    }

    public function planoConta(): BelongsTo
    {
        return $this->belongsTo(PlanoConta::class);
    }

    /**
     * Itens ativos com mínimo definido cujo saldo chegou ao mínimo ou abaixo dele.
     *
     * @param  Builder<ItemEstoque>  $query
     * @return Builder<ItemEstoque>
     */
    public function scopeAbaixoDoMinimo(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('estoque_minimo', '>', 0)
            ->whereColumn('quantidade_atual', '<=', 'estoque_minimo');
    }

    public function abaixoDoMinimo(): bool
    {
        return $this->is_active && (float) $this->estoque_minimo > 0 && (float) $this->quantidade_atual <= (float) $this->estoque_minimo;
    }

    public function valorEmEstoque(): float
    {
        return round((float) $this->quantidade_atual * (float) $this->custo_medio, 2);
    }

    public function rotuloDaUnidade(): string
    {
        return self::UNIDADES_DE_MEDIDA[$this->unidade_medida] ?? $this->unidade_medida;
    }
}
