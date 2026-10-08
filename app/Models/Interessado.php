<?php

namespace App\Models;

use App\Enums\SituacaoDocumento;
use App\Enums\StatusVisitaInteressado;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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

    /** Motivos padronizados de encerramento por perda/descarte. */
    public const MOTIVOS_PERDA = [
        'Preço' => 'Preço / Questão financeira',
        'Concorrência' => 'Escolheu outra escola',
        'Distância' => 'Distância / Localização / Transporte',
        'Mudança' => 'Mudança de endereço / cidade',
        'Vagas Esgotadas' => 'Sem vagas na série ou turno pretendido',
        'Metodologia' => 'Incompatibilidade pedagógica / proposta de ensino',
        'Sem retorno' => 'Sem retorno aos contatos da escola',
        'Desistência' => 'Desistiu do processo de matrícula',
        'Outro' => 'Outro motivo',
    ];

    protected $fillable = ['pessoa_id', 'usuario_id', 'origem_interessado_id', 'campanha_marketing_id', 'utm_source', 'utm_medium', 'utm_campaign', 'status_interessado_id', 'token_documentos', 'token_documentos_expira_em', 'data_proximo_contato', 'observacoes', 'redes_sociais', 'valor_estimado', 'temperatura', 'lead_score', 'lead_score_atualizado_em', 'faixa_distancia_escola', 'meio_transporte', 'motivo_perda', 'concorrente_id', 'fator_decisivo_concorrente', 'detalhes_concorrencia', 'data_primeiro_contato', 'data_conversao', 'token_convite', 'token_convite_expira_em', 'token_convite_usado_em', 'dados_pre_matricula'];

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
                'concorrente_id',
                'fator_decisivo_concorrente',
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
            'token_documentos_expira_em' => 'datetime',
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

    /**
     * Contatos que contam como interação (exclui os registros automáticos do sistema/IA).
     */
    public function interacoes(): HasMany
    {
        return $this->hasMany(HistoricoContato::class)->interacoes();
    }

    /**
     * Última interação registrada. Ignora registros automáticos (régua, análises de IA): se contassem,
     * um e-mail disparado pelo sistema zeraria o "sem interação" e esconderia um lead parado.
     */
    public function ultimoHistorico(): HasOne
    {
        return $this->hasOne(HistoricoContato::class)
            ->ofMany(['id' => 'max'], fn (Builder $query) => $query->interacoes());
    }

    public function campanha(): BelongsTo
    {
        return $this->belongsTo(CampanhaMarketing::class, 'campanha_marketing_id');
    }

    public function concorrente(): BelongsTo
    {
        return $this->belongsTo(Concorrente::class, 'concorrente_id');
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
            ->whereDoesntHave('historicos', fn (Builder $q) => $q->interacoes()->where('data_contato', '>=', $limite))
            ->where(function (Builder $q) use ($limite) {
                $q->whereHas('historicos', fn (Builder $h) => $h->interacoes())
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
     * Retorna o total de contatos (interações) realizados com este lead, sem os registros automáticos.
     */
    public function totalContatos(): int
    {
        return $this->interacoes()->count();
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

    public function indicacao(): HasOne
    {
        return $this->hasOne(IndicacaoInteressado::class, 'interessado_id');
    }

    public function documentosInseridos(): HasMany
    {
        return $this->hasMany(DocumentoInserido::class, 'interessado_id');
    }

    /**
     * Retorna os IDs de cursos pretendidos pela família, consultando os dependentes
     * vinculados e as escolhas preliminares na pré-matrícula.
     *
     * @return list<int>
     */
    public function cursosPretendidosIds(): array
    {
        $cursosIds = $this->dependentes->map(fn ($d) => $d->serie?->curso_id)->filter();

        if (is_array($this->dados_pre_matricula) && ! empty($this->dados_pre_matricula['dependentes'])) {
            $seriesPreMatricula = collect($this->dados_pre_matricula['dependentes'])->pluck('serie_id')->filter();
            if ($seriesPreMatricula->isNotEmpty()) {
                $cursosPreMatricula = Serie::whereIn('id', $seriesPreMatricula)->pluck('curso_id')->filter();
                $cursosIds = $cursosIds->concat($cursosPreMatricula);
            }
        }

        return $cursosIds->unique()->values()->all();
    }

    /**
     * Retorna a coleção de tipos de documentos visíveis no portal da família para este interessado.
     * Considera estritamente os tipos vinculados aos cursos das séries pretendidas ou gerais (sem curso vinculado),
     * excluindo documentos com categoria de uso interno e documentos vinculados exclusivamente a outros cursos.
     */
    public function documentosRequeridos(?int $cursoId = null): Collection
    {
        $cursosIds = $cursoId ? [$cursoId] : $this->cursosPretendidosIds();

        $query = TipoDocumento::query()
            ->visivelPortalFamilia()
            ->paraCursos($cursosIds)
            ->with('cursos');

        return $query->orderBy('nome')->get();
    }

    /**
     * Tipos de documentos obrigatórios para emitir o Contrato Escolar e ativar a matrícula.
     */
    public function documentosContrato(?int $cursoId = null): Collection
    {
        return $this->documentosRequeridos($cursoId)->filter(fn (TipoDocumento $doc) => $doc->isObrigatorioContrato());
    }

    /**
     * Tipos de documentos obrigatórios para a vida acadêmica e histórico do aluno.
     */
    public function documentosHistorico(?int $cursoId = null): Collection
    {
        return $this->documentosRequeridos($cursoId)->filter(fn (TipoDocumento $doc) => $doc->isObrigatorioHistorico());
    }

    /**
     * Tipos de documentos opcionais/complementares disponibilizados para envio facultativo.
     */
    public function documentosOpcionais(?int $cursoId = null): Collection
    {
        return $this->documentosRequeridos($cursoId)->filter(fn (TipoDocumento $doc) => $doc->isOpcional());
    }

    /**
     * Verifica se todos os documentos obrigatórios para a liberação do contrato já foram enviados
     * pela família (em análise ou aprovados).
     */
    public function todosDocsContratoEntregues(?int $cursoId = null): bool
    {
        $docsObrigatorios = $this->documentosContrato($cursoId);

        if ($docsObrigatorios->isEmpty()) {
            return true;
        }

        $docsEnviadosIds = $this->documentosInseridos()
            ->whereIn('status', [SituacaoDocumento::EM_ANALISE, SituacaoDocumento::VERIFICADO])
            ->pluck('tipo_documento_id')
            ->unique();

        return $docsObrigatorios->every(fn (TipoDocumento $tipo) => $docsEnviadosIds->contains($tipo->id));
    }

    /**
     * Validade máxima, em dias, do link do portal de pré-admissão (teto de revogação). Cada vez que a equipe
     * gera/copia/envia o link a validade é renovada (janela deslizante), então um link esquecido ou enviado à
     * pessoa errada deixa de valer sozinho em, no máximo, este prazo contado da última ação da equipe. Um link
     * expirado nunca é "revivido": a equipe recebe um novo e a URL antiga continua morta.
     */
    public const DIAS_VALIDADE_TOKEN_DOCUMENTOS = 7;

    /**
     * Leads cujo link do portal de documentos corresponde ao token e ainda não expirou.
     * Token sem data de expiração (emitido antes do controle de validade) não é mais aceito: a equipe
     * reenvia o link e recebe um novo, com validade.
     */
    public function scopeComTokenDocumentosValido(Builder $query, string $token): Builder
    {
        return $query
            ->where('token_documentos', $token)
            ->where('token_documentos_expira_em', '>', now());
    }

    /**
     * Leads cujo convite legado (`/quero-matricular/convite/{token}`) ainda está dentro da validade. Só serve para
     * redirecionar links já enviados antes da unificação; o portal em si nunca aceita este token.
     */
    public function scopeComConviteLegadoVigente(Builder $query, string $token): Builder
    {
        return $query
            ->where('token_convite', $token)
            ->where('token_convite_expira_em', '>', now());
    }

    public function tokenDocumentosValido(): bool
    {
        return filled($this->token_documentos)
            && $this->token_documentos_expira_em !== null
            && $this->token_documentos_expira_em->isFuture();
    }

    /**
     * Retorna o token do portal de pré-admissão / documentos do candidato, renovando a validade (janela
     * deslizante de {@see self::DIAS_VALIDADE_TOKEN_DOCUMENTOS} dias). Se não há token, ou ele expirou (ou é
     * legado, sem validade), emite um novo — o anterior deixa de existir.
     */
    public function obterOuCriarTokenDocumentos(): string
    {
        if ($this->tokenDocumentosValido()) {
            $validade = now()->addDays(self::DIAS_VALIDADE_TOKEN_DOCUMENTOS);

            // Evita escrita a cada abertura de modal: só renova quando a validade já encurtou um dia.
            if ($this->token_documentos_expira_em->lt($validade->copy()->subDay())) {
                $this->update(['token_documentos_expira_em' => $validade]);
            }

            return $this->token_documentos;
        }

        return $this->emitirTokenDocumentos();
    }

    /**
     * Revoga na hora o link do portal e o convite legado e emite um novo link: a URL antiga passa a responder
     * "link expirado". Use quando o link foi enviado à pessoa errada ou vazou.
     */
    public function rotacionarTokenDocumentos(): string
    {
        $token = $this->emitirTokenDocumentos();

        $this->update(['token_convite' => null, 'token_convite_expira_em' => null]);

        return $token;
    }

    /**
     * Token do portal para quem abriu um convite legado (link antigo). Reaproveita o token em vigor sem renová-lo —
     * abrir um link antigo não pode estender a validade — e, se não há um, emite um que não ultrapassa a validade
     * do próprio convite.
     */
    public function tokenDocumentosParaConviteLegado(): string
    {
        if ($this->tokenDocumentosValido()) {
            return $this->token_documentos;
        }

        $teto = now()->addDays(self::DIAS_VALIDADE_TOKEN_DOCUMENTOS);
        $validade = $this->token_convite_expira_em !== null && $this->token_convite_expira_em->lt($teto)
            ? $this->token_convite_expira_em
            : $teto;

        return $this->emitirTokenDocumentos($validade);
    }

    private function emitirTokenDocumentos(?\DateTimeInterface $validade = null): string
    {
        do {
            $token = Str::random(48);
        } while (static::where('token_documentos', $token)->exists());

        $this->update([
            'token_documentos' => $token,
            'token_documentos_expira_em' => $validade ?? now()->addDays(self::DIAS_VALIDADE_TOKEN_DOCUMENTOS),
        ]);

        return $token;
    }

    /**
     * Retorna a URL pública completa do portal de pré-admissão / documentos do candidato.
     */
    public function urlPortalDocumentos(): string
    {
        $token = $this->obterOuCriarTokenDocumentos();

        return route('candidato.documentos.show', ['token' => $token]);
    }

    /**
     * Retorna estatísticas de progresso dos documentos do candidato.
     * Considera na contagem estritamente os documentos obrigatórios para contrato.
     *
     * @return array{
     *     total: int,
     *     enviados: int,
     *     aprovados: int,
     *     em_analise: int,
     *     rejeitados: int,
     *     pendentes: int,
     *     percentual: int,
     *     completo: bool
     * }
     */
    public function progressoDocumentos(?int $cursoId = null): array
    {
        $docsContrato = $this->documentosContrato($cursoId);
        $totalDocsContrato = $docsContrato->count();

        if ($totalDocsContrato === 0) {
            return [
                'total' => 0,
                'enviados' => 0,
                'aprovados' => 0,
                'em_analise' => 0,
                'rejeitados' => 0,
                'pendentes' => 0,
                'percentual' => 100,
                'completo' => true,
            ];
        }

        $docsContratoIds = $docsContrato->pluck('id')->all();

        $inseridos = $this->documentosInseridos()
            ->whereIn('tipo_documento_id', $docsContratoIds)
            ->get();

        $aprovadosIds = $inseridos
            ->where('status', SituacaoDocumento::VERIFICADO)
            ->pluck('tipo_documento_id')
            ->unique();

        $emAnaliseIds = $inseridos
            ->where('status', SituacaoDocumento::EM_ANALISE)
            ->reject(fn (DocumentoInserido $doc) => $aprovadosIds->contains($doc->tipo_documento_id))
            ->pluck('tipo_documento_id')
            ->unique();

        $rejeitadosIds = $inseridos
            ->where('status', SituacaoDocumento::REJEITADO)
            ->reject(fn (DocumentoInserido $doc) => $aprovadosIds->contains($doc->tipo_documento_id) || $emAnaliseIds->contains($doc->tipo_documento_id))
            ->pluck('tipo_documento_id')
            ->unique();

        $aprovadosCount = $aprovadosIds->count();
        $emAnaliseCount = $emAnaliseIds->count();
        $rejeitadosCount = $rejeitadosIds->count();
        $enviadosCount = $aprovadosCount + $emAnaliseCount;
        $pendentesCount = max(0, $totalDocsContrato - $enviadosCount);

        $percentual = round(($enviadosCount / $totalDocsContrato) * 100);

        return [
            'total' => $totalDocsContrato,
            'enviados' => $enviadosCount,
            'aprovados' => $aprovadosCount,
            'em_analise' => $emAnaliseCount,
            'rejeitados' => $rejeitadosCount,
            'pendentes' => $pendentesCount,
            'percentual' => min(100, (int) $percentual),
            'completo' => $enviadosCount >= $totalDocsContrato,
        ];
    }

    /**
     * Retorna a lista descritiva de dados cadastrais pendentes para a pré-admissão.
     *
     * @return list<string>
     */
    public function pendenciasDadosCadastrais(): array
    {
        if (filled($this->dados_pre_matricula) && ! empty($this->dados_pre_matricula['responsaveis'])) {
            return [];
        }

        $pendencias = [];

        $pessoa = $this->pessoa;
        if (blank($pessoa?->cpf)) {
            $pendencias[] = 'CPF do responsável';
        }
        if (blank($pessoa?->data_nascimento)) {
            $pendencias[] = 'Data de nascimento do responsável';
        }
        if (blank($pessoa?->telefone)) {
            $pendencias[] = 'Telefone do responsável';
        }

        $temEndereco = $pessoa && ($pessoa->relationLoaded('enderecos')
            ? $pessoa->enderecos->isNotEmpty()
            : $pessoa->enderecos()->exists());

        if (! $temEndereco) {
            $pendencias[] = 'Endereço residencial completo';
        }

        $temVinculo = is_array($this->dados_pre_matricula)
            && ! empty($this->dados_pre_matricula['responsaveis'][0]['tipo_vinculo_id']);

        if (! $temVinculo) {
            $pendencias[] = 'Grau de parentesco / vínculo';
        }

        foreach ($this->dependentes as $idx => $dependente) {
            $nome = $dependente->nome_crianca ?: ('Aluno #'.($idx + 1));
            if (blank($dependente->data_nascimento)) {
                $pendencias[] = "Data de nascimento de {$nome}";
            }
            if (blank($dependente->serie_id)) {
                $pendencias[] = "Série pretendida de {$nome}";
            }
        }

        if ($pendencias === []) {
            $pendencias[] = 'Confirmação e aceite da pré-admissão';
        }

        return $pendencias;
    }

    /**
     * Retorna os tipos de documentos obrigatórios para CONTRATO que ainda não foram
     * entregues pela família ou que foram rejeitados pela secretaria.
     */
    public function documentosContratoPendentes(?int $cursoId = null): Collection
    {
        $docsContrato = $this->documentosContrato($cursoId);

        if ($docsContrato->isEmpty()) {
            return collect();
        }

        $docsEnviadosValidosIds = $this->documentosInseridos()
            ->whereIn('status', [SituacaoDocumento::EM_ANALISE, SituacaoDocumento::VERIFICADO])
            ->pluck('tipo_documento_id')
            ->unique();

        return $docsContrato->filter(fn (TipoDocumento $tipo) => ! $docsEnviadosValidosIds->contains($tipo->id))->values();
    }

    /**
     * Retorna os tipos de documentos obrigatórios (contrato e histórico) que ainda não foram
     * entregues pela família ou que foram rejeitados pela secretaria.
     */
    public function documentosObrigatoriosPendentes(?int $cursoId = null): Collection
    {
        $docsObrigatorios = $this->documentosRequeridos($cursoId)
            ->filter(fn (TipoDocumento $doc) => $doc->isObrigatorioContrato() || $doc->isObrigatorioHistorico());

        if ($docsObrigatorios->isEmpty()) {
            return collect();
        }

        $docsEnviadosValidosIds = $this->documentosInseridos()
            ->whereIn('status', [SituacaoDocumento::EM_ANALISE, SituacaoDocumento::VERIFICADO])
            ->pluck('tipo_documento_id')
            ->unique();

        return $docsObrigatorios->filter(fn (TipoDocumento $tipo) => ! $docsEnviadosValidosIds->contains($tipo->id))->values();
    }

    /**
     * Retorna as pendências impeditivas para a liberação do Contrato Escolar.
     *
     * @return list<string>
     */
    public function pendenciasContrato(?int $cursoId = null): array
    {
        $pendencias = [];

        if (empty($this->dados_pre_matricula)) {
            $pendencias[] = 'Confirmação dos dados cadastrais';
        }

        $docsContratoPendentes = $this->documentosContratoPendentes($cursoId);

        foreach ($docsContratoPendentes as $doc) {
            $pendencias[] = "Documento: {$doc->nome}";
        }

        return $pendencias;
    }

    /**
     * Retorna um resumo estruturado de pendências para as abas do portal de admissão.
     * A aba 'documentos' contabiliza especificamente os documentos obrigatórios para contrato.
     *
     * @return array{
     *     dados: array{tem_pendencia: bool, quantidade: int, pendencias: list<string>},
     *     documentos: array{tem_pendencia: bool, quantidade: int, total_obrigatorios: int, enviados: int, pendencias: Collection},
     *     contrato: array{tem_pendencia: bool, quantidade: int, pendencias: list<string>}
     * }
     */
    public function resumoPendenciasPortal(?int $cursoId = null): array
    {
        $pendenciasDados = $this->pendenciasDadosCadastrais();
        $docsContratoPendentes = $this->documentosContratoPendentes($cursoId);
        $totalDocsContrato = $this->documentosContrato($cursoId)->count();
        $pendenciasContrato = $this->pendenciasContrato($cursoId);

        return [
            'dados' => [
                'tem_pendencia' => $pendenciasDados !== [],
                'quantidade' => count($pendenciasDados),
                'pendencias' => $pendenciasDados,
            ],
            'documentos' => [
                'tem_pendencia' => $docsContratoPendentes->isNotEmpty(),
                'quantidade' => $docsContratoPendentes->count(),
                'total_obrigatorios' => $totalDocsContrato,
                'enviados' => max(0, $totalDocsContrato - $docsContratoPendentes->count()),
                'pendencias' => $docsContratoPendentes,
            ],
            'contrato' => [
                'tem_pendencia' => $pendenciasContrato !== [],
                'quantidade' => count($pendenciasContrato),
                'pendencias' => $pendenciasContrato,
            ],
        ];
    }

    /**
     * Indica se a etapa de responsabilidade da família na pré-matrícula foi concluída:
     * dados cadastrais preenchidos e todos os documentos obrigatórios entregues e em análise ou aprovados.
     */
    public function isEtapaFamiliaConcluida(?int $cursoId = null): bool
    {
        $resumo = $this->resumoPendenciasPortal($cursoId);

        return ! $resumo['dados']['tem_pendencia'] && ! $resumo['documentos']['tem_pendencia'];
    }
}
