<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\ReageAoDocumentoAssinado;
use App\Enums\StatusCasoFrequencia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Caso de faltas excessivas de um aluno (Onda 57). Guarda a apuração do dia em que a coordenação o abriu, as tentativas de
 * contato com a família, o ofício ao Conselho Tutelar e a resposta. As regras vivem em
 * {@see \App\Services\FaltasExcessivasService}.
 */
class CasoFrequencia extends Model implements ReageAoDocumentoAssinado
{
    protected $table = 'casos_frequencia';

    protected $fillable = [
        'matricula_id', 'periodo_letivo_id', 'status', 'aulas_previstas', 'faltas', 'faltas_justificadas', 'limite_aulas',
        'percentual_faltas', 'conselho_tutelar_id', 'observacao', 'notificado_em', 'protocolo_externo', 'resposta',
        'respondido_em', 'encerrado_em', 'encerramento_motivo', 'aberto_por_user_id', 'notificado_por_user_id',
        'encerrado_por_user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusCasoFrequencia::class,
            'aulas_previstas' => 'integer',
            'faltas' => 'integer',
            'faltas_justificadas' => 'integer',
            'limite_aulas' => 'integer',
            'percentual_faltas' => 'decimal:2',
            'notificado_em' => 'datetime',
            'respondido_em' => 'datetime',
            'encerrado_em' => 'datetime',
            'observacao' => 'encrypted',
            'resposta' => 'encrypted',
        ];
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    public function periodoLetivo(): BelongsTo
    {
        return $this->belongsTo(PeriodoLetivo::class);
    }

    public function conselhoTutelar(): BelongsTo
    {
        return $this->belongsTo(ConselhoTutelar::class);
    }

    public function contatos(): HasMany
    {
        return $this->hasMany(ContatoCasoFrequencia::class)->orderBy('data')->orderBy('id');
    }

    public function abertoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aberto_por_user_id');
    }

    public function notificadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'notificado_por_user_id');
    }

    public function encerradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'encerrado_por_user_id');
    }

    /**
     * Ofícios mandados para assinatura (Onda 69), do mais recente para o mais antigo.
     *
     * @return MorphMany<DocumentoAssinatura, $this>
     */
    public function documentosAssinatura(): MorphMany
    {
        return $this->morphMany(DocumentoAssinatura::class, 'documentavel')->latest('id');
    }

    /**
     * O ofício foi assinado por todos: fica no histórico do caso. O envio ao Conselho continua sendo registrado à mão
     * ("Marcar como enviado"), porque o sistema não protocola nada fora da escola.
     */
    public function documentoAssinado(DocumentoAssinatura $documento): void
    {
        activity('frequencia')
            ->performedOn($this)
            ->withProperties(['matricula_id' => $this->matricula_id, 'documento_assinatura_id' => $documento->id])
            ->log('Ofício ao Conselho Tutelar assinado.');
    }

    /**
     * Casos que ainda pedem ação da escola.
     *
     * @param  Builder<CasoFrequencia>  $query
     * @return Builder<CasoFrequencia>
     */
    public function scopeAbertos(Builder $query): Builder
    {
        return $query->where('status', '!=', StatusCasoFrequencia::Encerrado->value);
    }

    public function estaAberto(): bool
    {
        return $this->status->estaAberto();
    }

    public function foiNotificado(): bool
    {
        return $this->notificado_em !== null;
    }
}
