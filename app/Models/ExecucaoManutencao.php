<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StatusExecucaoManutencao;
use Database\Factories\ExecucaoManutencaoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma ocorrência de um plano de manutenção: o vencimento previsto, a ordem de serviço aberta para ele e, depois, o
 * resultado do checklist.
 */
class ExecucaoManutencao extends Model
{
    /** @use HasFactory<ExecucaoManutencaoFactory> */
    use HasFactory;

    protected $table = 'execucoes_manutencao';

    protected $fillable = [
        'plano_manutencao_id',
        'ordem_servico_id',
        'data_prevista',
        'status',
        'concluida_em',
        'checklist_resultado',
        'observacoes',
        'registrado_por_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data_prevista' => 'date',
            'concluida_em' => 'date',
            'status' => StatusExecucaoManutencao::class,
            'checklist_resultado' => 'array',
        ];
    }

    public function plano(): BelongsTo
    {
        return $this->belongsTo(PlanoManutencao::class, 'plano_manutencao_id');
    }

    public function ordemServico(): BelongsTo
    {
        return $this->belongsTo(OrdemServico::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }

    public function estaPrevista(): bool
    {
        return $this->status === StatusExecucaoManutencao::Prevista;
    }
}
