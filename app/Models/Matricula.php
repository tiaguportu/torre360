<?php

namespace App\Models;

use App\Notifications\DocumentosPendentesNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class Matricula extends Model
{
    protected $table = 'matricula';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function ($matricula) {
            if (! $matricula->codigo) {
                $anoCorrente = now()->year;
                $ultimoNumero = static::where('codigo', 'like', $anoCorrente.'%')
                    ->count();

                $proximoNumero = $ultimoNumero + 1;
                $matricula->codigo = $anoCorrente.str_pad($proximoNumero, 6, '0', STR_PAD_LEFT);
            }
        });
    }

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class);
    }

    public function turma(): BelongsTo
    {
        return $this->belongsTo(Turma::class);
    }

    public function situacaoMatricula(): BelongsTo
    {
        return $this->belongsTo(SituacaoMatricula::class);
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

    const SITUACAO_PENDENTE = 1;
    const SITUACAO_ANALISE = 2;
    const SITUACAO_APROVADO = 3;
    const SITUACAO_REJEITADO = 4;

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

        // IDS dos documentos que já estão inseridos e NOT REJECTED
        $inseridosIds = $this->documentoInseridos()
            ->where('situacao_documento_inserido_id', '!=', self::SITUACAO_REJEITADO)
            ->pluck('tipo_documento_id')
            ->toArray();

        return $obrigatorios->reject(function ($doc) use ($inseridosIds) {
            return in_array($doc->id, $inseridosIds);
        });
    }

    /**
     * Retorna os documentos inseridos que foram rejeitados
     */
    public function getRejectedDocuments(): Collection
    {
        return $this->documentoInseridos()
            ->where('situacao_documento_inserido_id', self::SITUACAO_REJEITADO)
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

        return [
            'enviados' => $countSent,
            'falhas' => $falhas,
        ];
    }
}
