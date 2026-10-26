<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatusRequisicaoCompra;
use App\Services\ComprasService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Requisição de compra de um setor (Onda 38). Carrega o status do processo inteiro; as regras vivem em {@see ComprasService}.
 */
class RequisicaoCompra extends Model
{
    protected $table = 'requisicoes_compra';

    protected $fillable = [
        'numero', 'solicitante_user_id', 'centro_custo_id', 'plano_conta_id', 'titulo', 'justificativa', 'urgencia', 'status',
        'cotacao_dispensada_justificativa', 'motivo_recusa', 'motivo_cancelamento', 'enviada_em',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusRequisicaoCompra::class,
            'enviada_em' => 'datetime',
        ];
    }

    public function itens(): HasMany
    {
        return $this->hasMany(ItemRequisicaoCompra::class)->orderBy('ordem')->orderBy('id');
    }

    public function cotacoes(): HasMany
    {
        return $this->hasMany(Cotacao::class)->orderBy('id');
    }

    public function pedido(): HasOne
    {
        return $this->hasOne(PedidoCompra::class);
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitante_user_id');
    }

    public function centroCusto(): BelongsTo
    {
        return $this->belongsTo(CentroCusto::class);
    }

    public function planoConta(): BelongsTo
    {
        return $this->belongsTo(PlanoConta::class);
    }

    public function ehRascunho(): bool
    {
        return $this->status === StatusRequisicaoCompra::Rascunho;
    }

    public function urgente(): bool
    {
        return $this->urgencia === 'urgente';
    }

    /**
     * Estimativa da compra: quantidade × estimativa unitária dos itens que têm estimativa.
     */
    public function valorEstimado(): float
    {
        return round((float) $this->itens->sum(fn (ItemRequisicaoCompra $i): float => (float) $i->quantidade * (float) ($i->estimativa_unitaria ?? 0)), 2);
    }
}
