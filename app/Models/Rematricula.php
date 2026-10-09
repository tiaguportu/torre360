<?php

namespace App\Models;

use App\Enums\SituacaoMatricula;
use App\Enums\StatusRematricula;
use App\Services\RematriculaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Rematricula extends Model
{
    use HasFactory;

    protected $table = 'rematriculas';

    protected $fillable = ['periodo_rematricula_id', 'matricula_origem_id', 'turma_destino_id', 'serie_destino_id', 'turno_pretendido_id', 'solicitante_user_id', 'status', 'contrato_id', 'nova_matricula_id', 'observacoes', 'data_confirmacao'];

    protected static function booted(): void
    {
        // Qualquer caminho que marque a rematrícula como Cancelada (ação da tabela, formulário de edição,
        // código) libera a vaga da nova matrícula e cancela as faturas em aberto.
        static::updated(function (Rematricula $rematricula): void {
            if ($rematricula->wasChanged('status') && $rematricula->status === StatusRematricula::Cancelada) {
                app(RematriculaService::class)->aplicarCancelamento($rematricula);
            }
        });

        // Uma rematrícula já efetivada (com matrícula, contrato e vaga) não pode simplesmente sumir:
        // apagá-la deixaria a matrícula ocupando vaga sem nenhum vínculo. Cancele primeiro.
        static::deleting(function (Rematricula $rematricula): ?bool {
            return $rematricula->podeSerExcluida() ? null : false;
        });
    }

    /**
     * Famílias que já registraram a intenção pelo Portal e aguardam a secretaria escolher a turma.
     */
    public function scopeAguardandoTurma(Builder $query): Builder
    {
        return $query
            ->where('status', StatusRematricula::DadosConfirmados->value)
            ->whereNull('nova_matricula_id');
    }

    public function foiEfetivada(): bool
    {
        return $this->nova_matricula_id !== null;
    }

    public function estaCancelada(): bool
    {
        return $this->status === StatusRematricula::Cancelada;
    }

    /**
     * Só se exclui rematrícula que nunca virou matrícula ou que já foi cancelada (a vaga já foi liberada).
     */
    public function podeSerExcluida(): bool
    {
        return ! $this->foiEfetivada() || $this->estaCancelada();
    }

    /**
     * Conclui a rematrícula quando o contrato é assinado: marca como Confirmada e, se a nova matrícula
     * estava Pendente (aguardando a assinatura), ela passa a Ativa.
     *
     * Rematrícula cancelada não é reativada: o documento no Assinafy continua existindo e a família
     * ainda poderia assiná-lo depois do cancelamento.
     */
    public function confirmarPelaAssinatura(): void
    {
        if ($this->estaCancelada() || $this->status === StatusRematricula::Confirmada) {
            return;
        }

        DB::transaction(function (): void {
            $this->update([
                'status' => StatusRematricula::Confirmada,
                'data_confirmacao' => now(),
            ]);

            if ($this->nova_matricula_id) {
                Matricula::query()
                    ->whereKey($this->nova_matricula_id)
                    ->where('situacao', SituacaoMatricula::PENDENTE->value)
                    ->update(['situacao' => SituacaoMatricula::ATIVA->value]);
            }
        });
    }

    public function periodoRematricula(): BelongsTo
    {
        return $this->belongsTo(PeriodoRematricula::class);
    }

    public function matriculaOrigem(): BelongsTo
    {
        return $this->belongsTo(Matricula::class, 'matricula_origem_id');
    }

    public function turmaDestino(): BelongsTo
    {
        return $this->belongsTo(Turma::class, 'turma_destino_id');
    }

    public function serieDestino(): BelongsTo
    {
        return $this->belongsTo(Serie::class, 'serie_destino_id');
    }

    public function turnoPretendido(): BelongsTo
    {
        return $this->belongsTo(Turno::class, 'turno_pretendido_id');
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitante_user_id');
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function novaMatricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class, 'nova_matricula_id');
    }

    protected function casts(): array
    {
        return [
            'status' => StatusRematricula::class,
            'data_confirmacao' => 'datetime',
        ];
    }
}
