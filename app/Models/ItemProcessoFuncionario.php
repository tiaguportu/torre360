<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CategoriaItemProcessoFuncionario;
use App\Enums\StatusProcessoFuncionario;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma providência do processo de admissão ou desligamento. "Resolvido" é feito ou marcado "não se aplica" (com
 * justificativa): nos dois casos deixa de impedir a conclusão do processo.
 */
class ItemProcessoFuncionario extends Model
{
    protected $table = 'itens_processo_funcionario';

    protected $fillable = [
        'processo_id',
        'categoria',
        'titulo',
        'orientacao',
        'obrigatorio',
        'prazo',
        'ordem',
        'concluido_em',
        'nao_se_aplica',
        'observacao',
        'arquivo_path',
        'concluido_por_user_id',
    ];

    protected function casts(): array
    {
        return [
            'categoria' => CategoriaItemProcessoFuncionario::class,
            'obrigatorio' => 'boolean',
            'prazo' => 'date',
            'ordem' => 'integer',
            'concluido_em' => 'datetime',
            'nao_se_aplica' => 'boolean',
        ];
    }

    public function processo(): BelongsTo
    {
        return $this->belongsTo(ProcessoFuncionario::class, 'processo_id');
    }

    public function concluidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'concluido_por_user_id');
    }

    public function estaResolvido(): bool
    {
        return $this->concluido_em !== null;
    }

    public function estaAtrasado(?CarbonInterface $em = null): bool
    {
        return ! $this->estaResolvido()
            && $this->prazo !== null
            && $this->prazo->toDateString() < ($em ?? today())->toDateString();
    }

    /**
     * Itens ainda não resolvidos.
     *
     * @param  Builder<ItemProcessoFuncionario>  $query
     * @return Builder<ItemProcessoFuncionario>
     */
    public function scopePendentes(Builder $query): Builder
    {
        return $query->whereNull('concluido_em');
    }

    /**
     * Itens pendentes com o prazo vencido, de processos que ainda estão em andamento.
     *
     * @param  Builder<ItemProcessoFuncionario>  $query
     * @return Builder<ItemProcessoFuncionario>
     */
    public function scopeAtrasados(Builder $query, ?CarbonInterface $em = null): Builder
    {
        return $query
            ->whereNull('concluido_em')
            ->whereNotNull('prazo')
            ->whereDate('prazo', '<', ($em ?? today())->toDateString())
            ->whereHas('processo', fn (Builder $processo) => $processo->where('status', StatusProcessoFuncionario::EmAndamento->value));
    }
}
