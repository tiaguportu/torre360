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
        $pessoasEnvolvidas = collect();

        // 1. O Professor
        if ($this->professor) {
            $pessoasEnvolvidas->push($this->professor);
        }

        // 2. O Aluno e seus responsáveis (via Matrícula)
        if ($this->matricula) {
            $pessoasEnvolvidas = $pessoasEnvolvidas->concat($this->matricula->getNotificationRecipients());
        }

        // Se a matrícula não retornar usuários (o que é raro no método dela), garantimos que pegamos pelo menos os usuários das pessoas
        return User::query()
            ->whereHas('pessoas', fn ($query) => $query->whereIn('pessoa.id', $pessoasEnvolvidas->pluck('id')->unique()))
            ->whereNotNull('email')
            ->get()
            ->unique('id');
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
