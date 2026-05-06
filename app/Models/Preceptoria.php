<?php

namespace App\Models;

use App\Notifications\Preceptorias\LembretePreceptoriaNotification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;

class Preceptoria extends Model
{
    use HasFactory;

    protected $table = 'preceptoria';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'hora_inicio' => 'datetime',
            'hora_fim' => 'datetime',
        ];
    }

    public function professor(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'professor_id');
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    public function relatorios(): HasMany
    {
        return $this->hasMany(RelatorioPreceptoria::class);
    }

    public function cicloPreceptoria(): BelongsTo
    {
        return $this->belongsTo(CicloPreceptoria::class);
    }

    /**
     * Label de exibição amigável.
     */
    /**
     * Verifica se a preceptoria está completamente agendada (data, horário, professor e matrícula).
     */
    public function isCompletamenteAgendada(): bool
    {
        return $this->data && $this->hora_inicio && $this->professor_id && $this->matricula_id;
    }

    /**
     * Verifica se o agendamento é para o dia seguinte.
     */
    public function isAgendamentoNoDiaSeguinte(): bool
    {
        if (! $this->data) {
            return false;
        }

        return Carbon::parse($this->data)->isTomorrow();
    }

    /**
     * Verifica se o agendamento é para uma data futura.
     */
    public function isAgendamentoFuturo(): bool
    {
        if (! $this->data) {
            return false;
        }

        return Carbon::parse($this->data)->isFuture();
    }

    /**
     * Retorna a lista de usuários (destinatários) que devem receber notificações desta preceptoria.
     * Inclui o professor, o aluno e os responsáveis do aluno.
     */
    public function getNotificationRecipients(): Collection
    {
        $destinatarios = collect();

        // Lista de pessoas que devem ser notificadas
        $pessoas = collect();

        // 1. O Professor
        if ($this->professor) {
            $pessoas->push($this->professor);
        }

        // 2. O Aluno e seus responsáveis (via Matrícula)
        if ($this->matricula && $this->matricula->pessoa) {
            $aluno = $this->matricula->pessoa;
            $pessoas->push($aluno);

            // Responsáveis do aluno
            foreach ($aluno->responsaveis as $resp) {
                $pessoas->push($resp);
            }
        }

        $pessoas = $pessoas->unique('id');

        foreach ($pessoas as $pessoa) {
            // Adicionar a própria pessoa se tiver e-mail (notificável via e-mail)
            if ($pessoa->email) {
                $destinatarios->push($pessoa);
            }

            // Adicionar todos os usuários vinculados (notificáveis via e-mail, push e sininho)
            foreach ($pessoa->users as $user) {
                if ($user->email) {
                    $destinatarios->push($user);
                }
            }
        }

        // Retorna coleção única baseada no e-mail para evitar duplicatas.
        // Se houver uma Pessoa e um User com o mesmo e-mail, priorizamos o User para garantir o envio via Push/Sininho.
        return $destinatarios->groupBy('email')->map(function ($group) {
            return $group->first(fn ($item) => $item instanceof User) ?? $group->first();
        })->values();
    }

    /**
     * Envia o lembrete de agendamento para os envolvidos.
     */
    public function relembrarAgendamento(): array
    {
        $destinatarios = $this->getNotificationRecipients();
        $countSent = 0;
        $falhas = [];

        foreach ($destinatarios as $user) {
            try {
                $user->notify(new LembretePreceptoriaNotification($this));
                $countSent++;
            } catch (\Throwable $e) {
                $errorMessage = $e->getMessage();
                $falhas[$user->email] = $errorMessage;
                Log::error("Erro ao enviar lembrete de preceptoria para {$user->email} na preceptoria {$this->id}: ".$errorMessage);
            }
        }

        if ($countSent > 0) {
            activity()
                ->performedOn($this)
                ->event('notificacao_lembrete_preceptoria')
                ->withProperties(['destinatarios_count' => $countSent])
                ->log("Enviado lembrete de agendamento de preceptoria para {$countSent} destinatário(s)");
        }

        return [
            'enviados' => $countSent,
            'falhas' => $falhas,
        ];
    }

    /**
     * Retorna a data da última notificação de lembrete enviada.
     */
    public function getLastLembreteNotificationDate(): ?Carbon
    {
        $lastActivity = Activity::query()
            ->where('subject_type', $this->getMorphClass())
            ->where('subject_id', $this->getKey())
            ->where('event', 'notificacao_lembrete_preceptoria')
            ->latest()
            ->first();

        return $lastActivity?->created_at;
    }

    public function getLabelExibicaoAttribute(): string
    {
        $data = $this->data ? Carbon::parse($this->data)->format('d/m/Y') : '';
        $inicio = $this->hora_inicio ? Carbon::parse($this->hora_inicio)->format('H:i') : '';

        return sprintf(
            '%s %s – %s',
            $data,
            $inicio,
            $this->professor?->nome ?? 'S/P'
        );
    }
}
