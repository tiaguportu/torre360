<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\ReageAoDocumentoAssinado;
use App\Enums\StatusReuniao;
use App\Enums\TipoReuniao;
use App\Models\Concerns\TemCopiaAssinada;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Reunião interna da escola (Ondas 27 e 32): conselho de classe, reunião pedagógica, devolutiva individual e formação.
 * Agendada → realizada (ata em aberto) → ata fechada, que não muda mais: a correção entra como adendo.
 */
class Reuniao extends Model implements ReageAoDocumentoAssinado
{
    use LogsActivity;
    use TemCopiaAssinada;

    protected $table = 'reunioes';

    protected $fillable = [
        'tipo',
        'titulo',
        'status',
        'confidencial',
        'inicio_em',
        'fim_em',
        'local',
        'sala_id',
        'reserva_espaco_id',
        'turma_id',
        'etapa_avaliativa_id',
        'organizador_user_id',
        'pauta',
        'ata',
        'realizada_em',
        'ata_fechada_em',
        'ata_fechada_por_user_id',
        'motivo_cancelamento',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoReuniao::class,
            'status' => StatusReuniao::class,
            'confidencial' => 'boolean',
            'inicio_em' => 'datetime',
            'fim_em' => 'datetime',
            'realizada_em' => 'datetime',
            'ata_fechada_em' => 'datetime',
        ];
    }

    /**
     * O log registra o fluxo da reunião, nunca a pauta nem o texto da ata (podem falar de crianças e de colegas).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['tipo', 'titulo', 'status', 'confidencial', 'inicio_em', 'turma_id', 'organizador_user_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('reuniao')
            ->setDescriptionForEvent(fn (string $evento): string => match ($evento) {
                'created' => 'Reunião agendada.',
                'updated' => 'Reunião atualizada.',
                'deleted' => 'Reunião excluída.',
                default => "Reunião: {$evento}",
            });
    }

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class);
    }

    public function reservaEspaco(): BelongsTo
    {
        return $this->belongsTo(ReservaEspaco::class, 'reserva_espaco_id');
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function etapaAvaliativa(): BelongsTo
    {
        return $this->belongsTo(EtapaAvaliativa::class, 'etapa_avaliativa_id');
    }

    public function organizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizador_user_id');
    }

    public function ataFechadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ata_fechada_por_user_id');
    }

    public function participantes(): HasMany
    {
        return $this->hasMany(ReuniaoParticipante::class, 'reuniao_id');
    }

    public function alunos(): HasMany
    {
        return $this->hasMany(ReuniaoAluno::class, 'reuniao_id')->orderBy('ordem')->orderBy('id');
    }

    public function encaminhamentos(): HasMany
    {
        return $this->hasMany(ReuniaoEncaminhamento::class, 'reuniao_id')->orderBy('prazo')->orderBy('id');
    }

    public function adendos(): HasMany
    {
        return $this->hasMany(ReuniaoAdendo::class, 'reuniao_id')->orderBy('id');
    }

    /**
     * A reunião sigilosa (e a devolutiva individual, sempre) só aparece para quem organiza e para quem participa. Nem a
     * administração a vê por padrão: o dado pessoal fica restrito a quem tem motivo para conhecê-lo.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisiveisPara(Builder $query, User $user): Builder
    {
        $veTodas = $user->can('VerTodas:Reuniao');

        return $query->where(function (Builder $q) use ($user, $veTodas): void {
            $q->where('organizador_user_id', $user->id)
                ->orWhereHas('participantes', fn (Builder $p) => $p->where('user_id', $user->id));

            if ($veTodas) {
                $q->orWhere(fn (Builder $aberta) => $aberta->where('confidencial', false)->where('tipo', '!=', TipoReuniao::Devolutiva->value));
            }
        });
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeDoTipo(Builder $query, TipoReuniao $tipo): Builder
    {
        return $query->where('tipo', $tipo->value);
    }

    public function estaAgendada(): bool
    {
        return $this->status === StatusReuniao::Agendada;
    }

    public function foiRealizada(): bool
    {
        return $this->status === StatusReuniao::Realizada;
    }

    public function ataEstaFechada(): bool
    {
        return $this->status === StatusReuniao::AtaFechada;
    }

    public function estaCancelada(): bool
    {
        return $this->status === StatusReuniao::Cancelada;
    }

    /**
     * A reunião já começou e a ata ainda não foi fechada: é a hora de registrar presença, escrever a ata e deliberar.
     */
    public function emAndamento(): bool
    {
        return $this->foiRealizada() || ($this->estaAgendada() && $this->inicio_em->isPast());
    }

    public function ehSigilosa(): bool
    {
        return $this->confidencial || $this->tipo->sempreConfidencial();
    }

    public function ehConselho(): bool
    {
        return $this->tipo->temAlunos();
    }

    public function ehOrganizador(User $user): bool
    {
        return $this->organizador_user_id !== null && (int) $this->organizador_user_id === (int) $user->id;
    }

    public function participaDe(User $user): bool
    {
        return $this->participantes()->where('user_id', $user->id)->exists();
    }

    public function ehPresidente(User $user): bool
    {
        return $this->participantes()->where('user_id', $user->id)->where('papel', ReuniaoParticipante::PAPEL_PRESIDENTE)->exists();
    }
}
