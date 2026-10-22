<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MotivoDesligamentoFuncionario;
use App\Enums\StatusProcessoFuncionario;
use App\Enums\TipoProcessoFuncionario;
use App\Services\ProcessoFuncionarioService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Admissão ou desligamento de um funcionário, com a lista de providências ({@see ItemProcessoFuncionario}). As regras vivem
 * em {@see ProcessoFuncionarioService}.
 */
class ProcessoFuncionario extends Model
{
    protected $table = 'processos_funcionario';

    protected $fillable = [
        'funcionario_id',
        'tipo',
        'status',
        'data_prevista',
        'motivo',
        'observacao',
        'iniciado_por_user_id',
        'concluido_por_user_id',
        'concluido_em',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoProcessoFuncionario::class,
            'status' => StatusProcessoFuncionario::class,
            'motivo' => MotivoDesligamentoFuncionario::class,
            'data_prevista' => 'date',
            'observacao' => 'encrypted',
            'concluido_em' => 'datetime',
        ];
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(ItemProcessoFuncionario::class, 'processo_id')->orderBy('ordem')->orderBy('id');
    }

    public function iniciadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'iniciado_por_user_id');
    }

    public function concluidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'concluido_por_user_id');
    }

    /**
     * @param  Builder<ProcessoFuncionario>  $query
     * @return Builder<ProcessoFuncionario>
     */
    public function scopeEmAndamento(Builder $query): Builder
    {
        return $query->where('status', StatusProcessoFuncionario::EmAndamento->value);
    }

    public function estaEmAndamento(): bool
    {
        return $this->status === StatusProcessoFuncionario::EmAndamento;
    }

    public function estaConcluido(): bool
    {
        return $this->status === StatusProcessoFuncionario::Concluido;
    }

    public function ehDesligamento(): bool
    {
        return $this->tipo === TipoProcessoFuncionario::Desligamento;
    }
}
