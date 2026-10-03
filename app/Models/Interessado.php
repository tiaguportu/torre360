<?php

namespace App\Models;

use App\Enums\StatusVisitaInteressado;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Interessado extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'interessado';

    /** Redes sociais aceitas em `redes_sociais` (chave => rótulo). */
    public const REDES_SOCIAIS = [
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'linkedin' => 'LinkedIn',
        'tiktok' => 'TikTok',
        'x' => 'X (Twitter)',
        'youtube' => 'YouTube',
        'outra' => 'Outra',
    ];

    protected $fillable = ['pessoa_id', 'usuario_id', 'origem_interessado_id', 'campanha_marketing_id', 'utm_source', 'utm_medium', 'utm_campaign', 'status_interessado_id', 'data_proximo_contato', 'observacoes', 'redes_sociais', 'valor_estimado', 'temperatura', 'lead_score', 'lead_score_atualizado_em', 'faixa_distancia_escola', 'meio_transporte', 'motivo_perda', 'data_primeiro_contato', 'data_conversao', 'token_convite', 'token_convite_expira_em', 'token_convite_usado_em', 'dados_pre_matricula'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'status_interessado_id',
                'origem_interessado_id',
                'campanha_marketing_id',
                'usuario_id',
                'temperatura',
                'valor_estimado',
                'motivo_perda',
                'data_proximo_contato',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('crm');
    }

    protected function casts(): array
    {
        return [
            'data_proximo_contato' => 'datetime',
            'data_primeiro_contato' => 'datetime',
            'data_conversao' => 'datetime',
            'lead_score_atualizado_em' => 'datetime',
            'valor_estimado' => 'decimal:2',
            'redes_sociais' => 'array',
            'token_convite_expira_em' => 'datetime',
            'token_convite_usado_em' => 'datetime',
            'dados_pre_matricula' => 'array',
        ];
    }

    // ─── Relationships ──────────────────────────────────────────

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function origem(): BelongsTo
    {
        return $this->belongsTo(OrigemInteressado::class, 'origem_interessado_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(StatusInteressado::class, 'status_interessado_id');
    }

    public function dependentes(): HasMany
    {
        return $this->hasMany(InteressadoDependente::class);
    }

    public function historicos(): HasMany
    {
        return $this->hasMany(HistoricoContato::class);
    }

    public function ultimoHistorico(): HasOne
    {
        return $this->hasOne(HistoricoContato::class)->latestOfMany();
    }

    public function campanha(): BelongsTo
    {
        return $this->belongsTo(CampanhaMarketing::class, 'campanha_marketing_id');
    }

    public function visitas(): HasMany
    {
        return $this->hasMany(VisitaInteressado::class);
    }

    /**
     * Próxima visita agendada (futura), usada para lembretes e mensagens.
     */
    public function proximaVisita(): HasOne
    {
        return $this->hasOne(VisitaInteressado::class)
            ->ofMany(
                ['data_hora' => 'min'],
                fn (Builder $query) => $query->agendadas()->where('data_hora', '>=', now())
            );
    }

    /**
     * Última visita realizada pelo interessado.
     */
    public function ultimaVisitaRealizada(): HasOne
    {
        return $this->hasOne(VisitaInteressado::class)
            ->ofMany(
                ['data_hora' => 'max'],
                fn (Builder $query) => $query->where('status', StatusVisitaInteressado::Realizada)
            );
    }

    /**
     * Última visita registrada (qualquer status).
     */
    public function ultimaVisita(): HasOne
    {
        return $this->hasOne(VisitaInteressado::class)->latestOfMany('data_hora');
    }

    // ─── Scopes ─────────────────────────────────────────────────

    /**
     * Filtra leads que não estão em status final (ganho/perdido).
     */
    public function scopeAtivos(Builder $query): Builder
    {
        return $query->whereHas('status', fn (Builder $q) => $q->where('is_final', false));
    }

    /**
     * Filtra leads com contato atrasado.
     */
    public function scopePrecisaContato(Builder $query): Builder
    {
        return $query->whereNotNull('data_proximo_contato')
            ->where('data_proximo_contato', '<', now());
    }

    /**
     * Filtra leads por consultor responsável.
     */
    public function scopeDoConsultor(Builder $query, int $usuarioId): Builder
    {
        return $query->where('usuario_id', $usuarioId);
    }

    /**
     * Filtra leads sem qualquer interação registrada nos últimos 7 dias.
     */
    public function scopeEstagnados(Builder $query, int $dias = 7): Builder
    {
        $limite = now()->subDays($dias);

        return $query
            ->whereDoesntHave('historicos', fn (Builder $q) => $q->where('data_contato', '>=', $limite))
            ->where(function (Builder $q) use ($limite) {
                $q->whereHas('historicos')
                    ->orWhere('created_at', '<=', $limite);
            });
    }

    // ─── Business Methods ───────────────────────────────────────

    /**
     * Verifica se o lead precisa de contato urgente.
     */
    public function precisaDeContato(): bool
    {
        if (! $this->data_proximo_contato) {
            return false;
        }

        $dataProximo = Carbon::parse($this->data_proximo_contato);
        $ultimoContato = $this->ultimoHistorico?->created_at;

        // Se a data do próximo contato já passou (atraso temporal)
        if ($dataProximo->isPast()) {
            return true;
        }

        // Se a data do próximo contato for anterior ao último contato realizado (agendamento desatualizado)
        if ($ultimoContato && $dataProximo->lt($ultimoContato)) {
            return true;
        }

        return false;
    }

    /**
     * Calcula quantos dias o lead está no funil de vendas.
     */
    public function diasNoFunil(): int
    {
        return (int) $this->created_at->diffInDays(now());
    }

    /**
     * Calcula quantos dias se passaram desde a última interação registrada
     * (ou desde a criação do lead, se nunca houve interação).
     */
    public function diasSemInteracao(): int
    {
        $ultima = $this->ultimoHistorico?->data_contato ?? $this->created_at;

        return (int) Carbon::parse($ultima)->diffInDays(now());
    }

    /**
     * Verifica se o lead está estagnado (sem interação há 7 dias ou mais).
     */
    public function estaEstagnado(int $dias = 7): bool
    {
        return $this->diasSemInteracao() >= $dias;
    }

    /**
     * Retorna o total de contatos realizados com este lead.
     */
    public function totalContatos(): int
    {
        return $this->historicos()->count();
    }

    /**
     * Verifica se o link de convite de matrícula online ainda pode ser usado: existe,
     * não expirou e ainda não foi usado.
     */
    public function conviteValido(): bool
    {
        return $this->token_convite !== null
            && $this->token_convite_usado_em === null
            && $this->token_convite_expira_em !== null
            && $this->token_convite_expira_em->isFuture();
    }
}
