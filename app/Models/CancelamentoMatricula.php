<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\ReageAoDocumentoAssinado;
use App\Enums\MotivoCancelamentoMatricula;
use App\Enums\OrigemCancelamentoMatricula;
use App\Enums\StatusCancelamentoMatricula;
use App\Services\CancelamentoMatriculaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Pedido de cancelamento de matrícula e o distrato que o fecha (Onda 61). As regras vivem em
 * {@see CancelamentoMatriculaService}; este model só guarda o estado e responde quem pode ver o quê.
 */
class CancelamentoMatricula extends Model implements ReageAoDocumentoAssinado
{
    protected $table = 'cancelamentos_matricula';

    protected $fillable = [
        'matricula_id',
        'origem',
        'motivo',
        'motivo_detalhe',
        'data_efetiva',
        'status',
        'decisoes',
        'valor_devido',
        'multa_rescisoria',
        'valor_a_devolver',
        'gerar_transferencia',
        'escola_destino',
        'observacao',
        'motivo_recusa',
        'distrato_gerado_em',
        'distrato_assinado_path',
        'devolucao_realizada_em',
        'transferencia_escolar_id',
        'solicitado_por_user_id',
        'decidido_por_user_id',
        'decidido_em',
        'concluido_por_user_id',
        'concluido_em',
    ];

    protected function casts(): array
    {
        return [
            'origem' => OrigemCancelamentoMatricula::class,
            'motivo' => MotivoCancelamentoMatricula::class,
            'status' => StatusCancelamentoMatricula::class,
            'motivo_detalhe' => 'encrypted',
            'data_efetiva' => 'date',
            'decisoes' => 'array',
            'valor_devido' => 'decimal:2',
            'multa_rescisoria' => 'decimal:2',
            'valor_a_devolver' => 'decimal:2',
            'gerar_transferencia' => 'boolean',
            'distrato_gerado_em' => 'datetime',
            'devolucao_realizada_em' => 'date',
            'decidido_em' => 'datetime',
            'concluido_em' => 'datetime',
        ];
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class, 'matricula_id');
    }

    public function transferenciaEscolar(): BelongsTo
    {
        return $this->belongsTo(TransferenciaEscolar::class, 'transferencia_escolar_id');
    }

    public function solicitadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_por_user_id');
    }

    public function decididoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decidido_por_user_id');
    }

    public function concluidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'concluido_por_user_id');
    }

    /**
     * Distratos mandados para assinatura eletrônica (Onda 69), do mais recente para o mais antigo.
     *
     * @return MorphMany<DocumentoAssinatura, $this>
     */
    public function documentosAssinatura(): MorphMany
    {
        return $this->morphMany(DocumentoAssinatura::class, 'documentavel')->latest('id');
    }

    /**
     * O distrato foi assinado eletronicamente: a cópia certificada passa a ser a cópia assinada do pedido, e a conclusão
     * deixa de esperar o anexo. A cópia em papel que já estivesse anexada não é apagada.
     */
    public function documentoAssinado(DocumentoAssinatura $documento): void
    {
        if (! in_array($this->status, [StatusCancelamentoMatricula::Aprovado, StatusCancelamentoMatricula::Concluido], true)
            || blank($documento->arquivo_assinado_path)
            || $this->distrato_assinado_path === $documento->arquivo_assinado_path) {
            return;
        }

        $this->update(['distrato_assinado_path' => $documento->arquivo_assinado_path]);

        activity('secretaria')
            ->performedOn($this)
            ->withProperties(['matricula_id' => $this->matricula_id, 'documento_assinatura_id' => $documento->id])
            ->log('Distrato assinado eletronicamente.');
    }

    /**
     * Pedidos em andamento (solicitados ou aprovados e ainda não concluídos).
     *
     * @param  Builder<CancelamentoMatricula>  $query
     * @return Builder<CancelamentoMatricula>
     */
    public function scopeAbertos(Builder $query): Builder
    {
        return $query->whereIn('status', [StatusCancelamentoMatricula::Solicitado->value, StatusCancelamentoMatricula::Aprovado->value]);
    }

    /**
     * Concluídos com dinheiro a devolver que ainda não foi devolvido (a mesma regra de {@see devolucaoPendente()}).
     *
     * @param  Builder<CancelamentoMatricula>  $query
     * @return Builder<CancelamentoMatricula>
     */
    public function scopeComDevolucaoPendente(Builder $query): Builder
    {
        return $query
            ->where('status', StatusCancelamentoMatricula::Concluido->value)
            ->where('valor_a_devolver', '>', 0)
            ->whereNull('devolucao_realizada_em');
    }

    public function estaAberto(): bool
    {
        return $this->status->estaAberto();
    }

    public function estaSolicitado(): bool
    {
        return $this->status === StatusCancelamentoMatricula::Solicitado;
    }

    public function estaAprovado(): bool
    {
        return $this->status === StatusCancelamentoMatricula::Aprovado;
    }

    public function estaConcluido(): bool
    {
        return $this->status === StatusCancelamentoMatricula::Concluido;
    }

    public function temDistratoAssinado(): bool
    {
        return filled($this->distrato_assinado_path);
    }

    /**
     * O distrato só existe depois da aprovação: é quando as decisões e os valores ficam definidos.
     */
    public function temDistrato(): bool
    {
        return $this->estaAprovado() || $this->estaConcluido();
    }

    /**
     * Concluído com dinheiro a devolver que ainda não foi devolvido.
     */
    public function devolucaoPendente(): bool
    {
        return $this->estaConcluido()
            && (float) $this->valor_a_devolver > 0
            && $this->devolucao_realizada_em === null;
    }

    /**
     * Quem pode ver o pedido e o distrato: a equipe com `View:CancelamentoMatricula` ou a família do aluno (responsável
     * pelo aluno, responsável financeiro do contrato ou o próprio aluno maior de idade). O professor não entra só por
     * ser da equipe: o pedido traz motivo, valores devidos e dados de contrato.
     */
    public function isAccessibleBy(User $user): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->can('View:CancelamentoMatricula')) {
            return true;
        }

        return app(CancelamentoMatriculaService::class)->familiaPodeAgir($this->matricula, $user);
    }
}
