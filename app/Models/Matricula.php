<?php

namespace App\Models;

use App\Enums\SituacaoDocumento;
use App\Enums\SituacaoMatricula;
use App\Notifications\DocumentosPendentesNotification;
use App\Notifications\Preceptorias\PossibilidadePreceptoriaNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;

class Matricula extends Model
{
    use HasFactory;

    protected $table = 'matricula';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'situacao' => SituacaoMatricula::class,
        ];
    }

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class);
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function periodoLetivo(): BelongsTo
    {
        return $this->belongsTo(PeriodoLetivo::class);
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function notas(): HasMany
    {
        return $this->hasMany(Nota::class);
    }

    public function documentoInseridos(): HasMany
    {
        return $this->hasMany(DocumentoInserido::class, 'matricula_id');
    }

    public function frequenciaEscolars(): HasMany
    {
        return $this->hasMany(FrequenciaEscolar::class);
    }

    public function preceptorias(): HasMany
    {
        return $this->hasMany(Preceptoria::class);
    }

    /**
     * Verifica se a matrícula possui uma preceptoria agendada para o futuro.
     */
    public function hasActivePreceptoria(): bool
    {
        return $this->preceptorias()
            ->where('data', '>=', now()->toDateString())
            ->exists();
    }

    /**
     * Verifica se existem janelas de preceptoria disponíveis para agendamento.
     */
    public function hasAvailablePreceptoriaWindows(): bool
    {
        return Preceptoria::query()
            ->whereNull('matricula_id')
            ->where('data', '>=', now()->toDateString())
            ->exists();
    }

    public function tiposDocumentos(): BelongsToMany
    {
        return $this->belongsToMany(TipoDocumento::class, 'tipo_documento_matricula');
    }

    /**
     * Verifica se faltam documentos obrigatórios para esta matrícula.
     */
    public function hasMissingMandatoryDocuments(): bool
    {
        return $this->getMissingMandatoryDocumentsCount() > 0;
    }

    /**
     * Verifica se há pendências de documentos (faltando ou rejeitados).
     */
    public function hasPendingIssues(): bool
    {
        return $this->getMissingMandatoryDocuments()->isNotEmpty() || $this->getRejectedDocuments()->isNotEmpty();
    }

    /**
     * Retorna a coleção de documentos obrigatórios que faltam ou foram rejeitados.
     * Considera documentos vinculados ao Curso, à Turma ou à Matrícula diretamente.
     *
     * @return Collection<TipoDocumento>
     */
    public function getMissingMandatoryDocuments(): Collection
    {
        $documentosRequeridos = collect();

        // 1. Documentos vinculados ao Curso
        if ($curso = $this->turma?->serie?->curso) {
            $documentosRequeridos = $documentosRequeridos->concat($curso->documentos);
        }

        // 2. Documentos vinculados à Turma
        if ($this->turma) {
            $documentosRequeridos = $documentosRequeridos->concat($this->turma->tiposDocumentos);
        }

        // 3. Documentos vinculados à Matrícula
        $documentosRequeridos = $documentosRequeridos->concat($this->tiposDocumentos);

        // Remover duplicados por ID e filtrar apenas obrigatórios
        $obrigatorios = $documentosRequeridos->unique('id')->filter(fn ($doc) => $doc->flag_obrigatorio);

        if ($obrigatorios->isEmpty()) {
            return collect();
        }

        // IDS dos documentos que já estão inseridos e NÃO REJEITADOS
        $inseridosIds = $this->documentoInseridos()
            ->where('status', '!=', SituacaoDocumento::REJEITADO)
            ->pluck('tipo_documento_id')
            ->toArray();

        return $obrigatorios->reject(function ($doc) use ($inseridosIds) {
            return in_array($doc->id, $inseridosIds);
        });
    }

    /**
     * Retorna os documentos inseridos que foram rejeitados (apenas se forem obrigatórios)
     */
    public function getRejectedDocuments(): Collection
    {
        return $this->documentoInseridos()
            ->where('status', SituacaoDocumento::REJEITADO)
            ->whereHas('tipoDocumento', fn ($query) => $query->where('flag_obrigatorio', true))
            ->with('tipoDocumento')
            ->get();
    }

    /**
     * Retorna a quantidade de documentos obrigatórios que faltam para esta matrícula.
     */
    public function getMissingMandatoryDocumentsCount(): int
    {
        return $this->getMissingMandatoryDocuments()->count();
    }

    /**
     * Retorna a lista de usuários (destinatários) que devem receber notificações desta matrícula.
     * Inclui o aluno e todos os responsáveis financeiros do contrato.
     *
     * @return Collection<User>
     */
    public function getNotificationRecipients(): Collection
    {
        $pessoasEnvolvidas = collect();

        // 1. O Aluno
        if ($this->pessoa) {
            $pessoasEnvolvidas->push($this->pessoa);
        }

        // 2. Responsáveis Financeiros do Contrato
        if ($this->contrato) {
            foreach ($this->contrato->responsaveisFinanceiros as $resp) {
                if ($resp->pessoa) {
                    $pessoasEnvolvidas->push($resp->pessoa);
                }
            }
        }

        // 3. Responsáveis Gerais do Aluno
        if ($this->pessoa) {
            foreach ($this->pessoa->responsaveis as $resp) {
                $pessoasEnvolvidas->push($resp);
            }
        }

        if ($pessoasEnvolvidas->isEmpty()) {
            return collect();
        }

        // Pegar todos os usuários vinculados a essas pessoas que possuem e-mail
        return User::query()
            ->whereHas('pessoas', fn ($query) => $query->whereIn('pessoa.id', $pessoasEnvolvidas->pluck('id')->unique()))
            ->whereNotNull('email')
            ->get()
            ->unique('id');
    }

    public function lastNotification(): MorphOne
    {
        return $this->morphOne(Activity::class, 'subject')
            ->where('event', 'notificacao_pendencia')
            ->latest();
    }

    /**
     * Retorna a data da última notificação de pendência enviada.
     */
    public function getLastPendingNotificationDate(): ?Carbon
    {
        $lastActivity = Activity::query()
            ->where('subject_type', $this->getMorphClass())
            ->where('subject_id', $this->getKey())
            ->where(function ($query) {
                $query->where('event', 'notificacao_pendencia')
                    ->orWhere('description', 'like', 'Enviada notifica%pendência%');
            })
            ->latest()
            ->first();

        return $lastActivity?->created_at;
    }

    /**
     * Envia notificação de documentos pendentes aos destinatários identificados.
     *
     * @return array{enviados: int, falhas: array<string, string>}
     */
    public function notifyMissingMandatoryDocuments(): array
    {
        $destinatarios = $this->getNotificationRecipients();
        $countSent = 0;
        $falhas = [];

        foreach ($destinatarios as $user) {
            try {
                $user->notify(new DocumentosPendentesNotification($this));
                $countSent++;
            } catch (\Throwable $e) {
                $errorMessage = $e->getMessage();
                $falhas[$user->email] = $errorMessage;
                Log::error("Erro ao enviar notificação de documentos pendentes para {$user->email} na matrícula {$this->id}: ".$errorMessage);
            }
        }

        if ($countSent > 0) {
            activity()
                ->performedOn($this)
                ->event('notificacao_pendencia')
                ->withProperties(['destinatarios_count' => $countSent])
                ->log("Enviada notificação (E-mail e Push) de pendência de documentos para {$countSent} destinatário(s)");
        }

        return [
            'enviados' => $countSent,
            'falhas' => $falhas,
        ];
    }

    /**
     * Envia notificação de possibilidade de agendamento de preceptoria.
     *
     * @return array{enviados: int, falhas: array<string, string>}
     */
    public function notifyPossibilityPreceptoria(): array
    {
        $destinatarios = $this->getNotificationRecipients();
        $countSent = 0;
        $falhas = [];

        foreach ($destinatarios as $user) {
            try {
                $user->notify(new PossibilidadePreceptoriaNotification($this));
                $countSent++;
            } catch (\Throwable $e) {
                $errorMessage = $e->getMessage();
                $falhas[$user->email] = $errorMessage;
                Log::error("Erro ao enviar notificação de possibilidade de preceptoria para {$user->email} na matrícula {$this->id}: ".$errorMessage);
            }
        }

        if ($countSent > 0) {
            activity()
                ->performedOn($this)
                ->event('notificacao_preceptoria_disponivel')
                ->withProperties(['destinatarios_count' => $countSent])
                ->log("Enviada notificação de possibilidade de agendamento de preceptoria para {$countSent} destinatário(s)");
        }

        return [
            'enviados' => $countSent,
            'falhas' => $falhas,
        ];
    }

    public function getLabelExibicaoAttribute(): string
    {
        return sprintf(
            '%s - %s - %s',
            $this->periodoLetivo?->nome ?? 'S/P',
            $this->turma?->nome ?? 'S/T',
            $this->pessoa?->nome ?? 'S/A'
        );
    }
}
