<?php

namespace App\Models;

use App\Enums\SituacaoMatricula;
use App\Enums\StatusTransferencia;
use App\Enums\TipoTemplateDocumento;
use App\Enums\TipoTransferencia;
use App\Services\DocumentoService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferenciaEscolar extends Model
{
    use HasFactory;

    protected $table = 'transferencias_escolares';

    protected $fillable = [
        'matricula_id',
        'tipo',
        'escola_externa_nome',
        'escola_externa_cidade',
        'escola_externa_uf',
        'data',
        'motivo',
        'status',
        'historico_recebido',
        'solicitacao_documento_id',
        'historico_escolar_ano_id',
        'observacoes',
        'criado_por_user_id',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoTransferencia::class,
            'status' => StatusTransferencia::class,
            'data' => 'date',
            'historico_recebido' => 'boolean',
        ];
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class, 'matricula_id');
    }

    public function solicitacaoDocumento(): BelongsTo
    {
        return $this->belongsTo(SolicitacaoDocumento::class, 'solicitacao_documento_id');
    }

    public function historicoEscolarAno(): BelongsTo
    {
        return $this->belongsTo(HistoricoEscolarAno::class, 'historico_escolar_ano_id');
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por_user_id');
    }

    /**
     * Encerra um processo de Saída: emite a Declaração de Transferência (reaproveitando
     * `DocumentoService`, mesmo motor usado pelas demais declarações — sem bloqueio por
     * pendência financeira, igual à regra já aplicada para esse tipo de documento) e
     * fecha a matrícula como Cancelada (mesma convenção que `HistoricoEscolarService` já
     * usa para derivar "Transferido" no histórico).
     *
     * @throws \DomainException quando não há um TemplateDocumento de Declaração de
     *                          Transferência ativo cadastrado.
     */
    public function concluirSaida(): void
    {
        $template = TemplateDocumento::where('tipo', TipoTemplateDocumento::DeclaracaoTransferencia)
            ->where('is_ativo', true)
            ->first();

        if (! $template) {
            throw new \DomainException('Não há um modelo de Declaração de Transferência ativo cadastrado em Modelos de Documento.');
        }

        $solicitacao = app(DocumentoService::class)->emitirDocumento(
            $this->matricula,
            $template,
            $this->motivo,
            $this->criadoPor,
        );

        $this->matricula->update(['situacao' => SituacaoMatricula::CANCELADA]);

        $this->update([
            'status' => StatusTransferencia::Concluida,
            'solicitacao_documento_id' => $solicitacao->id,
        ]);
    }

    /**
     * Encerra um processo de Entrada depois que a secretaria lançou o(s) ano(s) externo(s)
     * no Histórico Escolar do aluno (tela já existente, `HistoricoEscolarForm`).
     */
    public function marcarHistoricoRecebido(?HistoricoEscolarAno $ano = null): void
    {
        $this->update([
            'historico_recebido' => true,
            'status' => StatusTransferencia::Concluida,
            'historico_escolar_ano_id' => $ano?->id ?? $this->historico_escolar_ano_id,
        ]);
    }
}
