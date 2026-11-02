<?php

namespace App\Models;

use App\Enums\PrioridadeChamado;
use App\Enums\ResultadoChamado;
use App\Enums\StatusChamado;
use App\Support\Requerimentos\CatalogoRequerimentos;
use App\Support\Requerimentos\TipoRequerimento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AtendimentoChamado extends Model
{
    use HasFactory;

    protected $table = 'atendimento_chamados';

    protected $fillable = [
        'protocolo',
        'setor_id',
        'categoria_id',
        'matricula_id',
        'solicitante_id',
        'responsavel_atendimento_id',
        'assunto',
        'tipo_requerimento',
        'dados',
        'restrito',
        'prioridade',
        'status',
        'prazo_primeira_resposta_em',
        'prazo_resolucao_em',
        'primeira_resposta_em',
        'aguardando_solicitante_desde',
        'resultado',
        'resultado_justificativa',
        'aplicado_em',
        'aplicado_por_user_id',
        'avaliacao_nota',
        'avaliacao_comentario',
        'fechado_em',
    ];

    protected function casts(): array
    {
        return [
            'prioridade' => PrioridadeChamado::class,
            'status' => StatusChamado::class,
            'resultado' => ResultadoChamado::class,
            'dados' => 'array',
            'restrito' => 'boolean',
            'prazo_primeira_resposta_em' => 'datetime',
            'prazo_resolucao_em' => 'datetime',
            'primeira_resposta_em' => 'datetime',
            'aguardando_solicitante_desde' => 'datetime',
            'aplicado_em' => 'datetime',
            'avaliacao_nota' => 'integer',
            'fechado_em' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $chamado) {
            if (empty($chamado->protocolo)) {
                $ano = date('Y');
                $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
                $chamado->protocolo = "ATD-{$ano}-{$random}";
            }
        });
    }

    public function setor(): BelongsTo
    {
        return $this->belongsTo(AtendimentoSetor::class, 'setor_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(AtendimentoCategoria::class, 'categoria_id');
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class, 'matricula_id');
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'solicitante_id');
    }

    public function responsavelAtendimento(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsavel_atendimento_id');
    }

    public function aplicadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aplicado_por_user_id');
    }

    public function mensagens(): HasMany
    {
        return $this->hasMany(AtendimentoMensagem::class, 'chamado_id')->oldest();
    }

    /**
     * O tipo de requerimento do catálogo, quando o chamado é um requerimento.
     */
    public function tipo(): ?TipoRequerimento
    {
        return $this->tipo_requerimento ? CatalogoRequerimentos::tipo($this->tipo_requerimento) : null;
    }

    public function ehRequerimento(): bool
    {
        return filled($this->tipo_requerimento);
    }

    /**
     * Chamado ainda em tratamento pela escola (não resolvido nem encerrado).
     */
    public function estaEmAberto(): bool
    {
        return in_array($this->status, [StatusChamado::Aberto, StatusChamado::EmAndamento, StatusChamado::AguardandoSolicitante], true);
    }

    /**
     * Passou do prazo de primeira resposta sem resposta, ou da resolução. Chamado que espera a família não conta.
     */
    public function prazoEstourado(): bool
    {
        if (! $this->estaEmAberto()) {
            return false;
        }

        if ($this->primeira_resposta_em === null && $this->prazo_primeira_resposta_em?->isPast()) {
            return true;
        }

        return $this->status !== StatusChamado::AguardandoSolicitante && (bool) $this->prazo_resolucao_em?->isPast();
    }

    /**
     * Chamado restrito (pedido do titular da LGPD) só aparece para quem tem a permissão própria.
     *
     * @param  Builder<self>  $consulta
     * @return Builder<self>
     */
    public function scopeRestritosVisiveisPara(Builder $consulta, ?User $usuario): Builder
    {
        if ($usuario?->can('VerRestritos:AtendimentoChamado')) {
            return $consulta;
        }

        return $consulta->where('restrito', false);
    }

    /**
     * @param  Builder<self>  $consulta
     * @return Builder<self>
     */
    public function scopeEmAberto(Builder $consulta): Builder
    {
        return $consulta->whereIn('status', [StatusChamado::Aberto->value, StatusChamado::EmAndamento->value, StatusChamado::AguardandoSolicitante->value]);
    }

    /**
     * Sem primeira resposta e com o prazo vencido, ou com a resolução vencida.
     *
     * @param  Builder<self>  $consulta
     * @return Builder<self>
     */
    public function scopeComPrazoEstourado(Builder $consulta): Builder
    {
        return $consulta->emAberto()->where(function (Builder $q) {
            $q->where(fn (Builder $a) => $a->whereNull('primeira_resposta_em')->where('prazo_primeira_resposta_em', '<', now()))
                ->orWhere(fn (Builder $b) => $b->where('status', '!=', StatusChamado::AguardandoSolicitante->value)->where('prazo_resolucao_em', '<', now()));
        });
    }
}
