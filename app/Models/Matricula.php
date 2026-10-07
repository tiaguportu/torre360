<?php

namespace App\Models;

use App\Enums\SituacaoDocumento;
use App\Enums\SituacaoMatricula;
use App\Enums\StatusFatura;
use App\Enums\TipoPendenciaMatricula;
use App\Notifications\DocumentosPendentesNotification;
use App\Notifications\Preceptorias\PossibilidadePreceptoriaNotification;
use App\Support\PendenciasMatricula;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;

class Matricula extends Model
{
    use HasFactory;

    protected $table = 'matricula';

    /**
     * Situações em que se espera que a matrícula tenha contrato gerado e assinado.
     *
     * @var array<int, SituacaoMatricula>
     */
    public const SITUACOES_QUE_EXIGEM_CONTRATO = [SituacaoMatricula::ATIVA, SituacaoMatricula::PENDENTE];

    protected $fillable = ['pessoa_id', 'turma_id', 'status', 'periodo_letivo_id', 'situacao', 'data_ativacao', 'data_desativacao', 'serie_id', 'risco_evasao_score', 'risco_evasao_atualizado_em'];

    protected function casts(): array
    {
        return [
            'situacao' => SituacaoMatricula::class,
            'data_ativacao' => 'date',
            'data_desativacao' => 'date',
            'risco_evasao_atualizado_em' => 'datetime',
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

    public function serie(): BelongsTo
    {
        return $this->belongsTo(Serie::class);
    }

    public function getSerieNomeAttribute(): ?string
    {
        return $this->serie?->nome ?? $this->turma?->serie?->nome;
    }

    public function periodoLetivo(): BelongsTo
    {
        return $this->belongsTo(PeriodoLetivo::class);
    }

    public function contrato(): HasOne
    {
        return $this->hasOne(Contrato::class);
    }

    public function rematriculas(): HasMany
    {
        return $this->hasMany(Rematricula::class, 'matricula_origem_id');
    }

    /**
     * Verifica se o usuário pode ver esta matrícula: equipe interna sempre pode;
     * aluno/responsável só se a matrícula for a própria ou de um dependente seu.
     */
    public function isAccessibleBy(User $user): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        $idsAcessiveis = $user->pessoasAcessiveis()->pluck('id');

        if ($idsAcessiveis->contains($this->pessoa_id)) {
            return true;
        }

        return $this->contrato?->responsaveisFinanceiros->pluck('pessoa_id')->intersect($idsAcessiveis)->isNotEmpty() ?? false;
    }

    public function notas(): HasMany
    {
        return $this->hasMany(Nota::class);
    }

    public function documentoInseridos(): HasMany
    {
        return $this->hasMany(DocumentoInserido::class, 'matricula_id');
    }

    public function documentosInseridos(): HasMany
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

    public function situacoesFinais(): HasMany
    {
        return $this->hasMany(SituacaoFinalDisciplina::class);
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
     * Verifica se a matrícula possui alguma preceptoria vinculada a ciclos vigentes.
     */
    public function hasPreceptoriaInActiveCycles(): bool
    {
        return $this->preceptorias()
            ->whereHas('cicloPreceptoria', fn ($query) => $query->vigentes())
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

        // 1. Documentos vinculados ao Curso da matrícula
        $curso = null;
        if ($this->relationLoaded('turma') && $this->turma) {
            $serie = $this->turma->relationLoaded('serie') ? $this->turma->serie : null;
            $curso = $serie && $serie->relationLoaded('curso') ? $serie->curso : $this->turma->serie?->curso;
        } elseif (! $this->relationLoaded('turma')) {
            $curso = $this->turma?->serie?->curso ?? ($this->relationLoaded('serie') ? $this->serie?->curso : $this->serie?->curso);
        }

        if ($curso) {
            $docsCurso = $curso->relationLoaded('documentos')
                ? $curso->documentos
                : $curso->documentos()->get();
            $documentosRequeridos = $documentosRequeridos->concat($docsCurso);
        }

        // 2. Documentos vinculados especificamente à Turma
        if ($this->relationLoaded('turma') && $this->turma) {
            $docsTurma = $this->turma->relationLoaded('tiposDocumentos')
                ? $this->turma->tiposDocumentos
                : $this->turma->tiposDocumentos()->get();
            $documentosRequeridos = $documentosRequeridos->concat($docsTurma);
        } elseif (! $this->relationLoaded('turma') && $this->turma) {
            $documentosRequeridos = $documentosRequeridos->concat($this->turma->tiposDocumentos);
        }

        // 3. Documentos vinculados diretamente à Matrícula
        $docsMatricula = $this->relationLoaded('tiposDocumentos')
            ? $this->tiposDocumentos
            : $this->tiposDocumentos()->get();
        $documentosRequeridos = $documentosRequeridos->concat($docsMatricula);

        // Remover duplicados por ID e filtrar apenas os obrigatórios
        $obrigatorios = $documentosRequeridos
            ->unique('id')
            ->filter(fn (TipoDocumento $doc) => (bool) $doc->flag_obrigatorio);

        if ($obrigatorios->isEmpty()) {
            return collect();
        }

        // IDS dos documentos que já estão inseridos e NÃO REJEITADOS
        $inseridosIds = $this->relationLoaded('documentoInseridos')
            ? $this->documentoInseridos
                ->reject(fn (DocumentoInserido $doc) => $doc->status === SituacaoDocumento::REJEITADO)
                ->pluck('tipo_documento_id')
                ->all()
            : $this->documentoInseridos()
                ->where('status', '!=', SituacaoDocumento::REJEITADO)
                ->pluck('tipo_documento_id')
                ->all();

        return $obrigatorios->reject(function ($doc) use ($inseridosIds) {
            return in_array($doc->id, $inseridosIds);
        })->values();
    }

    /**
     * Retorna os documentos obrigatórios especificamente para geração do Contrato Escolar
     * que ainda não foram entregues ou estão rejeitados, considerando os cursos vinculados.
     *
     * @return Collection<TipoDocumento>
     */
    public function getMissingContractDocuments(): Collection
    {
        return $this->getMissingMandatoryDocuments()
            ->filter(fn (TipoDocumento $doc) => $doc->isObrigatorioContrato() || ($doc->flag_obrigatorio && $doc->categoria_exigencia === null))
            ->values();
    }

    /**
     * Informa se há pendência de documentos que bloqueiam a emissão do contrato.
     */
    public function hasMissingContractDocuments(): bool
    {
        return $this->getMissingContractDocuments()->isNotEmpty();
    }

    /**
     * Retorna os documentos inseridos que foram rejeitados (apenas se forem obrigatórios)
     */
    public function getRejectedDocuments(): Collection
    {
        if ($this->relationLoaded('documentoInseridos')) {
            $rejeitados = $this->documentoInseridos
                ->filter(fn (DocumentoInserido $doc) => $doc->status === SituacaoDocumento::REJEITADO);

            if ($rejeitados->isEmpty()) {
                return collect();
            }

            return $rejeitados->loadMissing('tipoDocumento')
                ->filter(fn (DocumentoInserido $doc) => (bool) $doc->tipoDocumento?->flag_obrigatorio)
                ->values();
        }

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
     * Retorna a data da última notificação de possibilidade de preceptoria enviada.
     */
    public function getLastPreceptoriaNotificationDate(): ?Carbon
    {
        $lastActivity = Activity::query()
            ->where('subject_type', $this->getMorphClass())
            ->where('subject_id', $this->getKey())
            ->where('event', 'notificacao_preceptoria_disponivel')
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

    /**
     * Retorna a lista de pessoas com dados cadastrais ou endereço pendentes vinculadas a esta matrícula.
     *
     * @return Collection<int, array{tipo: string, pessoa: Pessoa, campos: array<string>}>
     */
    public function getPessoasComCadastroIncompleto(): Collection
    {
        $incompletas = collect();

        // 1. Aluno
        if ($this->pessoa && $this->pessoa->hasIncompleteCadastro()) {
            $incompletas->push([
                'tipo' => 'Aluno',
                'pessoa' => $this->pessoa,
                'campos' => $this->pessoa->getMissingCadastroFields(),
            ]);
        }

        // 2. Responsáveis do Aluno
        if ($this->pessoa) {
            $responsaveis = $this->pessoa->relationLoaded('responsaveis')
                ? $this->pessoa->responsaveis
                : $this->pessoa->responsaveis()->with('enderecos')->get();

            foreach ($responsaveis as $resp) {
                if ($resp->hasIncompleteCadastro()) {
                    $vinculoNome = 'Responsável';
                    if ($resp->pivot && $resp->pivot->tipo_vinculo_id) {
                        $vinculoNome = self::nomesTiposVinculo()[$resp->pivot->tipo_vinculo_id] ?? 'Responsável';
                    }

                    $incompletas->push([
                        'tipo' => $vinculoNome,
                        'pessoa' => $resp,
                        'campos' => $resp->getMissingCadastroFields(),
                    ]);
                }
            }
        }

        // 3. Responsáveis Financeiros do Contrato
        if ($this->contrato) {
            $rfList = $this->contrato->relationLoaded('responsaveisFinanceiros')
                ? $this->contrato->responsaveisFinanceiros
                : $this->contrato->responsaveisFinanceiros()->with(['pessoa.enderecos'])->get();

            foreach ($rfList as $rf) {
                if ($rf->pessoa && $rf->pessoa->hasIncompleteCadastro()) {
                    if (! $incompletas->contains(fn ($item) => $item['pessoa']->id === $rf->pessoa->id)) {
                        $incompletas->push([
                            'tipo' => 'Responsável Financeiro',
                            'pessoa' => $rf->pessoa,
                            'campos' => $rf->pessoa->getMissingCadastroFields(),
                        ]);
                    }
                }
            }
        }

        return $incompletas;
    }

    /**
     * Nomes dos tipos de vínculo por id, carregados uma única vez (e não uma query por responsável).
     * Usa o helper once() do Laravel para memoização no ciclo de vida da requisição.
     *
     * @return array<int, string>
     */
    private static function nomesTiposVinculo(): array
    {
        return once(fn (): array => TipoVinculo::query()->pluck('nome', 'id')->all());
    }

    /**
     * Verifica se a matrícula possui alguma pendência cadastral (no Aluno, nos Responsáveis ou no Responsável Financeiro).
     */
    public function hasIncompleteCadastro(): bool
    {
        return $this->getPessoasComCadastroIncompleto()->isNotEmpty();
    }

    /**
     * Scope para filtrar matrículas com pendência de cadastro em Aluno, Responsáveis ou Responsáveis Financeiros.
     */
    public function scopeComCadastroIncompleto(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereHas('pessoa', fn ($sub) => $sub->incompleto())
                ->orWhereHas('pessoa.responsaveis', fn ($sub) => $sub->incompleto())
                ->orWhereHas('contrato.responsaveisFinanceiros.pessoa', fn ($sub) => $sub->incompleto());
        });
    }

    /**
     * Scope para filtrar matrículas com cadastro completo (Aluno completo, e nenhum responsável ou financeiro incompleto).
     */
    public function scopeComCadastroCompleto(Builder $query): Builder
    {
        return $query->whereHas('pessoa', fn ($sub) => $sub->completo())
            ->whereDoesntHave('pessoa.responsaveis', fn ($sub) => $sub->incompleto())
            ->whereDoesntHave('contrato.responsaveisFinanceiros.pessoa', fn ($sub) => $sub->incompleto());
    }

    /**
     * Verifica se o aluno da matrícula não possui nenhum Pai, Mãe ou Responsável associado.
     * Aproveita a relação já carregada, quando houver.
     */
    public function estaSemResponsavel(): bool
    {
        if (! $this->pessoa) {
            return false;
        }

        return $this->pessoa->relationLoaded('responsaveis')
            ? $this->pessoa->responsaveis->isEmpty()
            : ! $this->pessoa->responsaveis()->exists();
    }

    /**
     * Resumo consolidado das pendências (responsáveis, cadastro e documentos).
     * Memoizado por instância: colunas, ações e modais da listagem compartilham o mesmo cálculo.
     */
    protected function pendencias(): Attribute
    {
        return Attribute::get(fn (): PendenciasMatricula => new PendenciasMatricula(
            semResponsavel: $this->estaSemResponsavel(),
            cadastrosIncompletos: $this->getPessoasComCadastroIncompleto(),
            documentosFaltantes: $this->getMissingMandatoryDocuments(),
            documentosRejeitados: $this->getRejectedDocuments(),
            contratoNaoGerado: $this->exigeContrato() && $this->contrato === null,
            contratoNaoAssinado: $this->exigeContrato() && $this->contrato !== null && ! $this->contrato->estaAssinado(),
        ));
    }

    /**
     * Verifica se, pela situação atual, a matrícula deve ter contrato gerado e assinado.
     */
    public function exigeContrato(): bool
    {
        return in_array($this->situacao, self::SITUACOES_QUE_EXIGEM_CONTRATO, true);
    }

    /**
     * Scope para matrículas cujo aluno não tem responsável associado.
     */
    public function scopeSemResponsavel(Builder $query): Builder
    {
        return $query->whereHas('pessoa', fn (Builder $sub) => $sub->whereDoesntHave('responsaveis'));
    }

    /**
     * Scope para matrículas cujo aluno tem ao menos um responsável associado.
     */
    public function scopeComResponsavel(Builder $query): Builder
    {
        return $query->whereHas('pessoa', fn (Builder $sub) => $sub->whereHas('responsaveis'));
    }

    /**
     * Scope para matrículas com algum documento obrigatório (do curso, da turma ou da matrícula)
     * ainda não inserido (documentos rejeitados contam como não inseridos).
     */
    public function scopeComDocumentosFaltando(Builder $query): Builder
    {
        $faltante = self::restricaoDocumentoObrigatorioFaltante();

        return $query->where(fn (Builder $q) => $q
            ->whereHas('turma.serie.curso.documentos', $faltante)
            ->orWhereHas('turma.tiposDocumentos', $faltante)
            ->orWhereHas('tiposDocumentos', $faltante));
    }

    /**
     * Scope para matrículas sem nenhum documento obrigatório faltando.
     */
    public function scopeSemDocumentosFaltando(Builder $query): Builder
    {
        $faltante = self::restricaoDocumentoObrigatorioFaltante();

        return $query
            ->whereDoesntHave('turma.serie.curso.documentos', $faltante)
            ->whereDoesntHave('turma.tiposDocumentos', $faltante)
            ->whereDoesntHave('tiposDocumentos', $faltante);
    }

    /**
     * Scope para matrículas com documento obrigatório rejeitado (aguardando reenvio).
     */
    public function scopeComDocumentosRejeitados(Builder $query): Builder
    {
        return $query->whereHas('documentoInseridos', fn (Builder $sub) => $sub
            ->where('status', SituacaoDocumento::REJEITADO)
            ->whereHas('tipoDocumento', fn (Builder $tipo) => $tipo->where('flag_obrigatorio', true)));
    }

    /**
     * Scope para matrículas em situação que exige contrato (ativas e pendentes).
     */
    public function scopeExigindoContrato(Builder $query): Builder
    {
        return $query->whereIn('situacao', self::SITUACOES_QUE_EXIGEM_CONTRATO);
    }

    /**
     * Scope para matrículas que exigem contrato e ainda não têm nenhum gerado.
     */
    public function scopeComContratoNaoGerado(Builder $query): Builder
    {
        return $query->exigindoContrato()->doesntHave('contrato');
    }

    /**
     * Scope para matrículas que exigem contrato e cujo contrato gerado ainda não foi assinado.
     */
    public function scopeComContratoNaoAssinado(Builder $query): Builder
    {
        return $query->exigindoContrato()
            ->whereHas('contrato', fn (Builder $contrato) => $contrato->naoAssinado());
    }

    /**
     * Scope para matrículas com um tipo específico de pendência.
     */
    public function scopeComPendencia(Builder $query, TipoPendenciaMatricula $tipo): Builder
    {
        return match ($tipo) {
            TipoPendenciaMatricula::SEM_RESPONSAVEL => $query->semResponsavel(),
            TipoPendenciaMatricula::CADASTRO_INCOMPLETO => $query->comCadastroIncompleto(),
            TipoPendenciaMatricula::DOCUMENTOS_FALTANDO => $query->comDocumentosFaltando(),
            TipoPendenciaMatricula::DOCUMENTOS_REJEITADOS => $query->comDocumentosRejeitados(),
            TipoPendenciaMatricula::CONTRATO_NAO_GERADO => $query->comContratoNaoGerado(),
            TipoPendenciaMatricula::CONTRATO_NAO_ASSINADO => $query->comContratoNaoAssinado(),
        };
    }

    /**
     * Scope para matrículas com qualquer tipo de pendência.
     */
    public function scopeComPendencias(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            foreach (TipoPendenciaMatricula::cases() as $tipo) {
                $q->orWhere(fn (Builder $sub) => $sub->comPendencia($tipo));
            }
        });
    }

    /**
     * Restrição aplicada ao tipo de documento: obrigatório e sem documento válido inserido na matrícula externa.
     */
    private static function restricaoDocumentoObrigatorioFaltante(): \Closure
    {
        return fn (Builder $tipo) => $tipo
            ->where('tipo_documento.flag_obrigatorio', true)
            ->whereNotExists(fn ($existente) => $existente
                ->from('documento_inserido')
                ->whereColumn('documento_inserido.tipo_documento_id', 'tipo_documento.id')
                ->whereColumn('documento_inserido.matricula_id', 'matricula.id')
                ->where('documento_inserido.status', '!=', SituacaoDocumento::REJEITADO->value));
    }

    /**
     * Verifica se o contrato da matrícula possui faturas vencidas em atraso ou não quitadas após a data de vencimento.
     */
    public function hasDebitosVencidos(): bool
    {
        if (! $this->contrato) {
            return false;
        }

        return $this->contrato->faturas()
            ->where(function (Builder $query) {
                $query->where('status', StatusFatura::Atrasado)
                    ->orWhere(function (Builder $q) {
                        $q->whereIn('status', [StatusFatura::Pendente, StatusFatura::Parcial])
                            ->whereDate('vencimento', '<', now()->toDateString());
                    });
            })
            ->exists();
    }

    /**
     * Retorna a quantidade de faturas com débitos vencidos.
     */
    public function getDebitosVencidosCount(): int
    {
        if (! $this->contrato) {
            return 0;
        }

        return $this->contrato->faturas()
            ->where(function (Builder $query) {
                $query->where('status', StatusFatura::Atrasado)
                    ->orWhere(function (Builder $q) {
                        $q->whereIn('status', [StatusFatura::Pendente, StatusFatura::Parcial])
                            ->whereDate('vencimento', '<', now()->toDateString());
                    });
            })
            ->count();
    }

    /**
     * Retorna o horário formatado das aulas da turma do aluno para declarações.
     */
    public function getHorarioAulasFormatado(): string
    {
        $turno = $this->turma?->turno;

        if ($turno && $turno->hora_inicio && $turno->hora_fim) {
            $inicio = substr((string) $turno->hora_inicio, 0, 5);
            $fim = substr((string) $turno->hora_fim, 0, 5);

            return "das {$inicio} às {$fim}";
        }

        $turnoNome = mb_strtolower($turno?->nome ?? '');

        if (str_contains($turnoNome, 'manhã') || str_contains($turnoNome, 'matutino')) {
            return 'das 07:15 às 12:35';
        }

        if (str_contains($turnoNome, 'tarde') || str_contains($turnoNome, 'vespertino')) {
            return 'das 13:15 às 18:35';
        }

        if (str_contains($turnoNome, 'noite') || str_contains($turnoNome, 'noturno')) {
            return 'das 19:00 às 22:30';
        }

        if (str_contains($turnoNome, 'integral')) {
            return 'das 07:30 às 17:30';
        }

        return 'em horário regulamentar escolar';
    }
}
