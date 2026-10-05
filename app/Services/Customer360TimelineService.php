<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SituacaoDocumento;
use App\Models\DocumentoInserido;
use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\StatusInteressado;
use App\Models\VisitaInteressado;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

class Customer360TimelineService
{
    /**
     * Mapeamento de status para nomes legíveis em cache de execução.
     *
     * @var array<int, string>|null
     */
    protected ?array $statusMap = null;

    /**
     * Retorna a lista unificada e normalizada de todos os eventos da Linha do Tempo 360°.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function obterTimeline(Interessado $interessado, string $filtroCategoria = 'todos', ?string $busca = null): Collection
    {
        $eventos = collect();

        // 1. Contatos e Mensagens (Humanos e IA)
        if (in_array($filtroCategoria, ['todos', 'contatos'], true)) {
            $eventos = $eventos->concat($this->coletarContatos($interessado));
        }

        // 2. Visitas Escolares e Pesquisas de Satisfação (NPS)
        if (in_array($filtroCategoria, ['todos', 'visitas'], true)) {
            $eventos = $eventos->concat($this->coletarVisitas($interessado));
        }

        // 3. Documentos de Pré-Admissão e Parecer Pericial de IA
        if (in_array($filtroCategoria, ['todos', 'documentos'], true)) {
            $eventos = $eventos->concat($this->coletarDocumentos($interessado));
        }

        // 4. Auditoria de Etapas, Mudança de Status e Perda no Funil (ActivityLog)
        if (in_array($filtroCategoria, ['todos', 'etapas'], true)) {
            $eventos = $eventos->concat($this->coletarAtividades($interessado));
        }

        // Ordenação decrescente pela data/hora do evento
        $eventosOrdenados = $eventos->sortByDesc(fn (array $evento) => $evento['data_hora'] instanceof Carbon ? $evento['data_hora']->timestamp : 0)->values();

        // Aplica busca textual se informada
        if (! blank($busca)) {
            $termo = mb_strtolower(trim($busca), 'UTF-8');
            $eventosOrdenados = $eventosOrdenados->filter(function (array $evento) use ($termo): bool {
                $textoParaBusca = mb_strtolower(
                    implode(' ', [
                        $evento['titulo'] ?? '',
                        $evento['subtitulo'] ?? '',
                        $evento['conteudo'] ?? '',
                        $evento['autor'] ?? '',
                        $evento['badge'] ?? '',
                        json_encode($evento['detalhes'] ?? [], JSON_UNESCAPED_UNICODE),
                    ]),
                    'UTF-8'
                );

                return str_contains($textoParaBusca, $termo);
            })->values();
        }

        return $eventosOrdenados;
    }

    /**
     * Retorna indicadores e métricas consolidadas do lead para o cabeçalho 360°.
     *
     * @return array<string, mixed>
     */
    public function obterResumoMetricas(Interessado $interessado): array
    {
        $interessado->loadMissing(['status', 'usuario', 'dependentes', 'pessoa']);

        $totalContatos = $interessado->historicos()->count();
        $totalVisitas = $interessado->visitas()->count();
        $totalDocumentos = $interessado->documentosInseridos()->count();
        $docsAprovados = $interessado->documentosInseridos()->where('status', SituacaoDocumento::VERIFICADO)->count();
        $docsComIa = $interessado->documentosInseridos()->whereNotNull('analisado_ia_em')->count();

        /** @var HistoricoContato|null $ultimoContato */
        $ultimoContato = $interessado->historicos()->latest('data_contato')->first();

        /** @var VisitaInteressado|null $ultimaVisita */
        $ultimaVisita = $interessado->visitas()->with('pesquisa')->latest('data_hora')->first();

        $dataProximo = $interessado->data_proximo_contato;
        $estaEmAtraso = $dataProximo !== null && $dataProximo->isPast();
        $diasAtraso = $estaEmAtraso ? (int) $dataProximo->diffInDays(now()) : 0;

        $npsVisita = null;
        if ($ultimaVisita?->pesquisa?->isRespondida()) {
            $npsVisita = [
                'nota' => $ultimaVisita->pesquisa->nota_nps,
                'classificacao' => $ultimaVisita->pesquisa->classificacaoNps(),
                'comentario' => $ultimaVisita->pesquisa->comentario,
            ];
        }

        return [
            'total_interacoes' => $totalContatos + $totalVisitas + $totalDocumentos,
            'total_contatos' => $totalContatos,
            'total_visitas' => $totalVisitas,
            'total_documentos' => $totalDocumentos,
            'docs_aprovados' => $docsAprovados,
            'docs_com_ia' => $docsComIa,
            'ultimo_contato_em' => $ultimoContato?->data_contato,
            'ultimo_contato_relativo' => $ultimoContato?->data_contato?->diffForHumans(),
            'proximo_contato_em' => $dataProximo,
            'esta_em_atraso' => $estaEmAtraso,
            'dias_atraso' => $diasAtraso,
            'nps_visita' => $npsVisita,
            'lead_score' => $interessado->lead_score ?? 0,
            'temperatura' => $interessado->temperatura ?? 'morno',
            'status_nome' => $interessado->status?->nome ?? 'Lead Ativo',
            'status_cor' => $interessado->status?->cor ?? 'primary',
            'consultor_nome' => $interessado->usuario?->name ?? 'Não atribuído',
            'telefone_contato' => $interessado->pessoa?->telefone,
        ];
    }

    /**
     * Coleta e normaliza o histórico de contatos.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function coletarContatos(Interessado $interessado): Collection
    {
        $contatos = $interessado->historicos()
            ->with(['tipoContato', 'usuario'])
            ->latest('data_contato')
            ->get();

        return $contatos->map(function (HistoricoContato $contato): array {
            $tipoNome = $contato->tipoContato?->nome ?? 'Contato';
            $tipoSlug = mb_strtolower($tipoNome, 'UTF-8');

            [$icone, $corIcone, $bgIcone] = match (true) {
                str_contains($tipoSlug, 'whatsapp') => ['heroicon-o-chat-bubble-left-ellipsis', 'text-emerald-600 dark:text-emerald-400', 'bg-emerald-100 dark:bg-emerald-950/60 border-emerald-300 dark:border-emerald-800'],
                str_contains($tipoSlug, 'liga') || str_contains($tipoSlug, 'telef') => ['heroicon-o-phone', 'text-sky-600 dark:text-sky-400', 'bg-sky-100 dark:bg-sky-950/60 border-sky-300 dark:border-sky-800'],
                str_contains($tipoSlug, 'mail') => ['heroicon-o-envelope', 'text-purple-600 dark:text-purple-400', 'bg-purple-100 dark:bg-purple-950/60 border-purple-300 dark:border-purple-800'],
                str_contains($tipoSlug, 'presen') => ['heroicon-o-user-group', 'text-amber-600 dark:text-amber-400', 'bg-amber-100 dark:bg-amber-950/60 border-amber-300 dark:border-amber-800'],
                default => ['heroicon-o-chat-bubble-bottom-center-text', 'text-indigo-600 dark:text-indigo-400', 'bg-indigo-100 dark:bg-indigo-950/60 border-indigo-300 dark:border-indigo-800'],
            };

            $resultadoLabel = $contato->resultado ? (HistoricoContato::RESULTADOS[$contato->resultado] ?? ucfirst($contato->resultado)) : null;
            $resultadoCor = match ($contato->resultado) {
                'agendou_visita', 'matriculou' => 'emerald',
                'retornar' => 'amber',
                'sem_interesse' => 'rose',
                default => 'gray',
            };

            $dataHora = $contato->data_contato ?? $contato->created_at ?? now();

            return [
                'id' => 'contato_'.$contato->id,
                'registro_id' => $contato->id,
                'tipo' => 'contato',
                'categoria' => 'contatos',
                'data_hora' => $dataHora,
                'data_formatada' => $dataHora->format('d/m/Y H:i'),
                'data_relativa' => $dataHora->diffForHumans(),
                'titulo' => $tipoNome,
                'subtitulo' => 'Registrado por '.($contato->usuario?->name ?? 'Usuário do Sistema'),
                'autor' => $contato->usuario?->name,
                'conteudo' => $contato->relato,
                'icone' => $icone,
                'cor_icone' => $corIcone,
                'bg_icone' => $bgIcone,
                'badge' => $resultadoLabel,
                'badge_cor' => $resultadoCor,
                'detalhes' => [
                    'duracao_minutos' => $contato->duracao_minutos,
                    'resultado' => $contato->resultado,
                ],
            ];
        });
    }

    /**
     * Coleta e normaliza as visitas escolares e pesquisas NPS.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function coletarVisitas(Interessado $interessado): Collection
    {
        $visitas = $interessado->visitas()
            ->with(['usuario', 'dependente', 'pesquisa'])
            ->latest('data_hora')
            ->get();

        return $visitas->map(function (VisitaInteressado $visita): array {
            $dataHora = $visita->data_hora ?? $visita->created_at ?? now();
            $statusLabel = $visita->status?->getLabel() ?? ucfirst($visita->status?->value ?? 'agendada');
            $statusCor = $visita->status?->getColor() ?? 'primary';

            $alunoInfo = $visita->dependente ? " • Candidato: {$visita->dependente->nome_crianca}" : '';
            $subtitulo = 'Consultor Anfitrião: '.($visita->usuario?->name ?? 'Equipe Comercial').$alunoInfo;

            $pesquisa = $visita->pesquisa;
            $temPesquisa = $pesquisa !== null;
            $pesquisaRespondida = $pesquisa?->isRespondida() ?? false;

            return [
                'id' => 'visita_'.$visita->id,
                'registro_id' => $visita->id,
                'tipo' => 'visita',
                'categoria' => 'visitas',
                'data_hora' => $dataHora,
                'data_formatada' => $dataHora->format('d/m/Y H:i'),
                'data_relativa' => $dataHora->diffForHumans(),
                'titulo' => 'Tour Escolar / Visita Presencial',
                'subtitulo' => $subtitulo,
                'autor' => $visita->usuario?->name,
                'conteudo' => $visita->observacoes,
                'icone' => 'heroicon-o-academic-cap',
                'cor_icone' => 'text-teal-600 dark:text-teal-400',
                'bg_icone' => 'bg-teal-100 dark:bg-teal-950/60 border-teal-300 dark:border-teal-800',
                'badge' => $statusLabel,
                'badge_cor' => $statusCor,
                'detalhes' => [
                    'status' => $visita->status?->value,
                    'tem_pesquisa' => $temPesquisa,
                    'pesquisa_respondida' => $pesquisaRespondida,
                    'nota_nps' => $pesquisa?->nota_nps,
                    'classificacao_nps' => $pesquisa?->classificacaoNps(),
                    'nota_atendimento' => $pesquisa?->nota_atendimento,
                    'nota_infraestrutura' => $pesquisa?->nota_infraestrutura,
                    'nota_proposta_pedagogica' => $pesquisa?->nota_proposta_pedagogica,
                    'comentario_pesquisa' => $pesquisa?->comentario,
                    'link_pesquisa' => $pesquisa?->urlPublica(),
                    'link_whatsapp' => $pesquisa?->linkWhatsapp(),
                ],
            ];
        });
    }

    /**
     * Coleta e normaliza os documentos de pré-admissão e análises de IA.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function coletarDocumentos(Interessado $interessado): Collection
    {
        $documentos = $interessado->documentosInseridos()
            ->with(['tipoDocumento', 'dependente'])
            ->latest('created_at')
            ->get();

        return $documentos->map(function (DocumentoInserido $doc): array {
            $dataHora = $doc->analisado_ia_em ?? $doc->created_at ?? now();
            $tipoNome = $doc->tipoDocumento?->nome ?? 'Documento';
            $alunoInfo = $doc->dependente ? " ({$doc->dependente->nome_crianca})" : '';

            $statusLabel = $doc->status?->getLabel() ?? 'Pendente';
            $statusCor = $doc->status?->getColor() ?? 'gray';

            $temAnaliseIa = $doc->temAnaliseIa();
            $nomeArquivo = $doc->nome_arquivo_original ?? basename((string) $doc->arquivo_path);

            return [
                'id' => 'doc_'.$doc->id,
                'registro_id' => $doc->id,
                'tipo' => 'documento',
                'categoria' => 'documentos',
                'data_hora' => $dataHora,
                'data_formatada' => $dataHora->format('d/m/Y H:i'),
                'data_relativa' => $dataHora->diffForHumans(),
                'titulo' => "Documento: {$tipoNome}{$alunoInfo}",
                'subtitulo' => "Arquivo recebido: {$nomeArquivo}",
                'autor' => 'Upload da Família / Secretaria',
                'conteudo' => $doc->observacoes,
                'icone' => 'heroicon-o-document-check',
                'cor_icone' => 'text-violet-600 dark:text-violet-400',
                'bg_icone' => 'bg-violet-100 dark:bg-violet-950/60 border-violet-300 dark:border-violet-800',
                'badge' => $statusLabel,
                'badge_cor' => $statusCor,
                'detalhes' => [
                    'tem_analise_ia' => $temAnaliseIa,
                    'resumo_ia' => $doc->resumoIa(),
                    'legivel_ia' => $doc->isLegivelIa(),
                    'confere_tipo_ia' => $doc->confereTipoIa(),
                    'score_confianca' => $doc->dados_ia['score_confianca'] ?? null,
                    'dados_extraidos' => $doc->dados_ia['dados_extraidos'] ?? [],
                    'alertas' => $doc->dados_ia['alertas'] ?? [],
                    'arquivo_path' => $doc->arquivo_path,
                ],
            ];
        });
    }

    /**
     * Coleta eventos de auditoria comercial do Spatie ActivityLog.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function coletarAtividades(Interessado $interessado): Collection
    {
        /** @var Collection<int, Activity> $atividades */
        $atividades = Activity::forSubject($interessado)
            ->where('log_name', 'crm')
            ->with('causer')
            ->latest('created_at')
            ->limit(40)
            ->get();

        $eventos = collect();

        foreach ($atividades as $activity) {
            $dataHora = $activity->created_at ?? now();
            $properties = $activity->properties?->toArray() ?? [];
            $attributes = $properties['attributes'] ?? [];
            $old = $properties['old'] ?? [];
            $causerName = $activity->causer?->name ?? 'Sistema / Automação';

            // Mudança de Status no Funil de Vendas / Kanban
            if (array_key_exists('status_interessado_id', $attributes)) {
                $statusOldId = $old['status_interessado_id'] ?? null;
                $statusNewId = $attributes['status_interessado_id'] ?? null;

                $statusOldNome = $this->obterNomeStatus($statusOldId);
                $statusNewNome = $this->obterNomeStatus($statusNewId);

                $eventos->push([
                    'id' => 'activity_status_'.$activity->id,
                    'registro_id' => $activity->id,
                    'tipo' => 'etapa',
                    'categoria' => 'etapas',
                    'data_hora' => $dataHora,
                    'data_formatada' => $dataHora->format('d/m/Y H:i'),
                    'data_relativa' => $dataHora->diffForHumans(),
                    'titulo' => "Avanço no Funil: Etapa alterada para [{$statusNewNome}]",
                    'subtitulo' => "Movido por {$causerName}",
                    'autor' => $causerName,
                    'conteudo' => $statusOldNome ? "Status alterado de '{$statusOldNome}' para '{$statusNewNome}'." : "Status inicial definido como '{$statusNewNome}'.",
                    'icone' => 'heroicon-o-arrows-right-left',
                    'cor_icone' => 'text-blue-600 dark:text-blue-400',
                    'bg_icone' => 'bg-blue-100 dark:bg-blue-950/60 border-blue-300 dark:border-blue-800',
                    'badge' => 'Etapa do Funil',
                    'badge_cor' => 'info',
                    'detalhes' => [
                        'status_anterior' => $statusOldNome,
                        'status_novo' => $statusNewNome,
                    ],
                ]);
            }

            // Motivo de Perda / Descarte
            if (array_key_exists('motivo_perda', $attributes) && ! blank($attributes['motivo_perda'])) {
                $motivo = $attributes['motivo_perda'];
                $motivoFormatado = Interessado::MOTIVOS_PERDA[$motivo] ?? $motivo;

                $eventos->push([
                    'id' => 'activity_perda_'.$activity->id,
                    'registro_id' => $activity->id,
                    'tipo' => 'etapa',
                    'categoria' => 'etapas',
                    'data_hora' => $dataHora,
                    'data_formatada' => $dataHora->format('d/m/Y H:i'),
                    'data_relativa' => $dataHora->diffForHumans(),
                    'titulo' => 'Lead Encerrado por Perda / Descarte',
                    'subtitulo' => "Registrado por {$causerName}",
                    'autor' => $causerName,
                    'conteudo' => "Motivo da perda selecionado: {$motivoFormatado}",
                    'icone' => 'heroicon-o-x-circle',
                    'cor_icone' => 'text-rose-600 dark:text-rose-400',
                    'bg_icone' => 'bg-rose-100 dark:bg-rose-950/60 border-rose-300 dark:border-rose-800',
                    'badge' => 'Lead Perdido',
                    'badge_cor' => 'danger',
                    'detalhes' => [
                        'motivo_perda' => $motivoFormatado,
                    ],
                ]);
            }

            // Mudança de Temperatura Comercial
            if (array_key_exists('temperatura', $attributes)) {
                $tempNova = ucfirst((string) ($attributes['temperatura'] ?? ''));
                $tempAntiga = ucfirst((string) ($old['temperatura'] ?? ''));

                $eventos->push([
                    'id' => 'activity_temp_'.$activity->id,
                    'registro_id' => $activity->id,
                    'tipo' => 'etapa',
                    'categoria' => 'etapas',
                    'data_hora' => $dataHora,
                    'data_formatada' => $dataHora->format('d/m/Y H:i'),
                    'data_relativa' => $dataHora->diffForHumans(),
                    'titulo' => "Temperatura Comercial Ajustada para {$tempNova}",
                    'subtitulo' => "Atualizado por {$causerName}",
                    'autor' => $causerName,
                    'conteudo' => $tempAntiga ? "Termômetro ajustado de {$tempAntiga} para {$tempNova}." : "Termômetro definido como {$tempNova}.",
                    'icone' => 'heroicon-o-fire',
                    'cor_icone' => 'text-amber-600 dark:text-amber-400',
                    'bg_icone' => 'bg-amber-100 dark:bg-amber-950/60 border-amber-300 dark:border-amber-800',
                    'badge' => 'Termômetro',
                    'badge_cor' => 'warning',
                    'detalhes' => [
                        'temperatura' => $tempNova,
                    ],
                ]);
            }
        }

        return $eventos;
    }

    /**
     * Auxiliar com cache para obter nome do status a partir do ID.
     */
    protected function obterNomeStatus(?int $statusId): ?string
    {
        if ($statusId === null) {
            return null;
        }

        if ($this->statusMap === null) {
            $this->statusMap = StatusInteressado::pluck('nome', 'id')->all();
        }

        return $this->statusMap[$statusId] ?? (string) $statusId;
    }
}
