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
     * Dias de validade do link do portal de pré-admissão. Cada vez que a equipe gera/copia o
     * link, a validade é renovada (janela deslizante); links esquecidos expiram sozinhos.
     */
    public const DIAS_VALIDADE_TOKEN_DOCUMENTOS = 90;

    /**
     * Leads cujo link do portal de documentos corresponde ao token e ainda não expirou.
     * Token sem data de expiração (legado) segue válido até ser renovado pela equipe.
     */
    public function scopeComTokenDocumentosValido(Builder $query, string $token): Builder
    {
        return $query
            ->where('token_documentos', $token)
            ->where(fn (Builder $q) => $q
                ->whereNull('token_documentos_expira_em')
                ->orWhere('token_documentos_expira_em', '>', now()));
    }

    /**
     * Retorna ou gera o token exclusivo para o portal de pré-admissão / documentos do candidato,
     * renovando a validade do link.
     */
    public function obterOuCriarTokenDocumentos(): string
    {
        $validade = now()->addDays(self::DIAS_VALIDADE_TOKEN_DOCUMENTOS);

        if (filled($this->token_documentos)) {
            // Evita escrita a cada abertura de modal: só renova quando a validade já encurtou um dia.
            if ($this->token_documentos_expira_em === null || $this->token_documentos_expira_em->lt($validade->copy()->subDay())) {
                $this->update(['token_documentos_expira_em' => $validade]);
            }

            return $this->token_documentos;
        }

        do {
            $token = Str::random(48);
        } while (static::where('token_documentos', $token)->exists());

        $this->update(['token_documentos' => $token, 'token_documentos_expira_em' => $validade]);

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
     */
    public function progressoDocumentos(): array
    {
        $requeridos = $this->documentosRequeridos();
        $totalRequeridos = $requeridos->count();

        $inseridos = $this->documentosInseridos()->with('tipoDocumento')->get();
        $aprovados = $inseridos->where('status', SituacaoDocumento::VERIFICADO)->count();
        $emAnalise = $inseridos->where('status', SituacaoDocumento::EM_ANALISE)->count();
        $rejeitados = $inseridos->where('status', SituacaoDocumento::REJEITADO)->count();

        $percentual = $totalRequeridos > 0 ? round(($aprovados / $totalRequeridos) * 100) : 0;

        return [
            'total' => $totalRequeridos,
            'enviados' => $inseridos->count(),
            'aprovados' => $aprovados,
            'em_analise' => $emAnalise,
            'rejeitados' => $rejeitados,
            'pendentes' => max(0, $totalRequeridos - $aprovados),
            'percentual' => min(100, (int) $percentual),
            'completo' => $totalRequeridos > 0 && $aprovados >= $totalRequeridos,
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
}
