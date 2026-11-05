<?php

namespace App\Services;

use App\Enums\SituacaoFinal;
use App\Enums\SituacaoMatricula;
use App\Models\Disciplina;
use App\Models\FrequenciaEscolar;
use App\Models\HistoricoEscolar;
use App\Models\HistoricoEscolarAno;
use App\Models\HistoricoEscolarDisciplina;
use App\Models\Matricula;
use App\Models\MatrizCurricular;
use App\Models\SituacaoFinalDisciplina;
use App\Models\TipoVinculo;
use App\Models\Unidade;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfInstance;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HistoricoEscolarService
{
    /** LDB (Lei 9.394/1996), art. 24, VI: frequência mínima de 75% do total de horas letivas. */
    private const FREQUENCIA_MINIMA = 75.00;

    /** Progressão parcial (dependência) do regimento: até este número de disciplinas reprovadas; acima, retenção. */
    private const MAX_DISCIPLINAS_DEPENDENCIA = 2;

    public function __construct(
        protected QrCodeService $qrCodeService,
        protected FechamentoCicloService $fechamentoCicloService,
        protected BoletimService $boletimService
    ) {}

    /**
     * Sincroniza e importa automaticamente os dados das matrículas internas do Torre360
     * para os registros de anos e disciplinas do Histórico Escolar.
     *
     * @return array{anos_sincronizados: int, disciplinas_sincronizadas: int}
     */
    public function sincronizarMatriculasInternas(HistoricoEscolar $historico): array
    {
        $aluno = $historico->pessoa;
        if (! $aluno) {
            return ['anos_sincronizados' => 0, 'disciplinas_sincronizadas' => 0];
        }

        $query = Matricula::query()
            ->where('pessoa_id', $aluno->id)
            ->whereNotNull('turma_id')
            // Vaga reservada e matrícula pendente ainda não cursaram o ano: não entram no histórico.
            ->whereNotIn('situacao', [SituacaoMatricula::RESERVA->value, SituacaoMatricula::PENDENTE->value])
            ->with(['turma.serie.curso', 'turma.disciplinas.areaConhecimento', 'periodoLetivo']);

        if ($historico->curso_id) {
            $query->whereHas('turma.serie', fn ($q) => $q->where('curso_id', $historico->curso_id));
        }

        $matriculas = $query->get()->sortBy(function (Matricula $m) {
            $ano = (int) ($m->periodoLetivo?->nome ?? ($m->data_ativacao ? Carbon::parse($m->data_ativacao)->year : $m->created_at->year));

            return $ano;
        });

        $totalAnos = 0;
        $totalDisciplinas = 0;
        $ordemAno = 1;

        $unidade = $historico->unidade ?? $aluno->matriculas()->latest()->first()?->turma?->serie?->curso?->unidade;
        $escolaNome = $unidade?->nome ?? config('app.name', 'Torre de Marfim');
        $escolaCidade = $unidade?->endereco?->cidade?->nome ?? 'Localidade';
        $escolaUf = $unidade?->endereco?->cidade?->estado?->sigla ?? 'UF';

        foreach ($matriculas as $matricula) {
            $anoLetivo = (int) ($matricula->periodoLetivo?->nome ?? ($matricula->data_ativacao ? Carbon::parse($matricula->data_ativacao)->year : $matricula->created_at->year));
            $serie = $matricula->turma?->serie;
            $serieNome = $serie?->nome ?? $matricula->turma?->nome ?? 'Série';

            // Carga horária e frequência
            $cargaHorariaTotal = (int) ($matricula->turma?->carga_horaria_total ?: 800);
            $diasLetivos = 200; // Padrão LDB

            $frequenciaPercentual = $this->frequenciaPercentual($matricula);

            $ordem = $ordemAno++;

            // O ano e as disciplinas entram juntos: a situação do ano só é conhecida depois de apurar as disciplinas.
            $totalDisciplinas += DB::transaction(function () use ($historico, $matricula, $anoLetivo, $serie, $serieNome, $ordem, $escolaNome, $escolaCidade, $escolaUf, $diasLetivos, $cargaHorariaTotal, $frequenciaPercentual): int {
                $historicoAno = HistoricoEscolarAno::updateOrCreate(
                    [
                        'historico_escolar_id' => $historico->id,
                        'matricula_id' => $matricula->id,
                    ],
                    [
                        'ano_letivo' => $anoLetivo,
                        'serie_id' => $serie?->id,
                        'serie_nome' => $serieNome,
                        'ordem' => $ordem,
                        'tipo' => 'interno',
                        'escola_nome' => $escolaNome,
                        'escola_cidade' => $escolaCidade,
                        'escola_uf' => $escolaUf,
                        'dias_letivos' => $diasLetivos,
                        'carga_horaria_total' => $cargaHorariaTotal,
                        'frequencia_percentual' => $frequenciaPercentual,
                    ]
                );

                $disciplinas = $this->sincronizarDisciplinas($historicoAno, $matricula);

                $historicoAno->update([
                    'situacao_ano' => $this->apurarSituacaoAno($matricula->situacao, $frequenciaPercentual, $disciplinas['reprovadas']),
                ]);

                return $disciplinas['total'];
            });

            $totalAnos++;
        }

        return [
            'anos_sincronizados' => $totalAnos,
            'disciplinas_sincronizadas' => $totalDisciplinas,
        ];
    }

    /**
     * Reapura os anos internos que o histórico já tem (frequência, disciplinas e resultado do ano), com as mesmas
     * regras da sincronização, mas sem criar ano novo, sem mexer na ordem e sem tocar nos anos externos. Ano de
     * matrícula em reserva ou pendente (gravado antes de essas matrículas saírem da sincronização) fica como está
     * e volta na lista para revisão manual.
     *
     * @return list<array{ano: HistoricoEscolarAno, frequencia_antes: ?float, frequencia_depois: ?float, antes: ?string, depois: ?string, disciplinas_alteradas: int, ignorado: ?string}>
     */
    public function reapurarAnosInternos(HistoricoEscolar $historico): array
    {
        $anos = $historico->anos()
            ->where('tipo', 'interno')
            ->whereNotNull('matricula_id')
            ->with(['matricula.turma.serie', 'matricula.turma.disciplinas.areaConhecimento', 'matricula.periodoLetivo'])
            ->orderBy('ordem')
            ->get();

        $resultado = [];

        foreach ($anos as $ano) {
            $matricula = $ano->matricula;
            $linha = [
                'ano' => $ano,
                'frequencia_antes' => $ano->frequencia_percentual !== null ? (float) $ano->frequencia_percentual : null,
                'frequencia_depois' => null,
                'antes' => $ano->situacao_ano,
                'depois' => null,
                'disciplinas_alteradas' => 0,
                'ignorado' => null,
            ];

            if (! $matricula?->turma) {
                $resultado[] = ['ignorado' => 'matrícula ou turma não encontrada'] + $linha;

                continue;
            }

            if (in_array($matricula->situacao, [SituacaoMatricula::RESERVA, SituacaoMatricula::PENDENTE], true)) {
                $resultado[] = ['ignorado' => 'matrícula em reserva ou pendente: revisar o ano à mão'] + $linha;

                continue;
            }

            $resultado[] = DB::transaction(function () use ($ano, $matricula, $linha): array {
                $disciplinasAntes = $ano->disciplinas()->pluck('situacao', 'disciplina_id')->all();
                $frequencia = $this->frequenciaPercentual($matricula);

                $ano->update(['frequencia_percentual' => $frequencia]);
                $disciplinas = $this->sincronizarDisciplinas($ano, $matricula);
                $ano->update(['situacao_ano' => $this->apurarSituacaoAno($matricula->situacao, $frequencia, $disciplinas['reprovadas'])]);

                $disciplinasDepois = $ano->disciplinas()->pluck('situacao', 'disciplina_id')->all();

                return [
                    'frequencia_depois' => $frequencia,
                    'depois' => $ano->situacao_ano,
                    'disciplinas_alteradas' => count(array_filter($disciplinasDepois, fn ($situacao, $id) => ($disciplinasAntes[$id] ?? null) !== $situacao, ARRAY_FILTER_USE_BOTH)),
                ] + $linha;
            });
        }

        return $resultado;
    }

    /**
     * Frequência do aluno no ano (%): a falta abonada vale presença, a só justificada continua sendo falta.
     */
    private function frequenciaPercentual(Matricula $matricula): float
    {
        $totalAulas = FrequenciaEscolar::where('matricula_id', $matricula->id)->count();
        $faltas = FrequenciaEscolar::where('matricula_id', $matricula->id)->contaComoFalta()->count();

        return $totalAulas > 0
            ? round((($totalAulas - $faltas) / $totalAulas) * 100, 2)
            : 100.00;
    }

    /**
     * Grava as disciplinas da turma no ano do histórico, já com a situação apurada.
     *
     * @return array{total: int, reprovadas: int}
     */
    private function sincronizarDisciplinas(HistoricoEscolarAno $historicoAno, Matricula $matricula): array
    {
        $serie = $matricula->turma?->serie;
        $disciplinas = $matricula->turma?->disciplinas ?? collect();
        $ordemDisc = 1;
        $reprovadas = 0;

        foreach ($disciplinas as $disciplina) {
            $areaNome = $disciplina->areaConhecimento?->nome ?? 'Base Nacional Comum';

            // Carga horária da disciplina a partir da matriz curricular ou semanal
            $matrizItem = $serie ? MatrizCurricular::where('serie_id', $serie->id)->where('disciplina_id', $disciplina->id)->first() : null;
            $chSemanal = $matrizItem?->carga_horaria_semanal ?? $disciplina->carga_horaria_semanal ?? 4;
            $chAnual = (int) ($chSemanal * 40); // 40 semanas letivas por ano (200 dias)

            ['nota' => $notaFinal, 'situacao' => $situacaoDisc] = $this->apurarDisciplina($matricula, $disciplina);

            if ($situacaoDisc === 'Reprovado') {
                $reprovadas++;
            }

            HistoricoEscolarDisciplina::updateOrCreate(
                [
                    'historico_escolar_ano_id' => $historicoAno->id,
                    'disciplina_id' => $disciplina->id,
                ],
                [
                    'disciplina_nome' => $disciplina->nome,
                    'area_conhecimento' => $areaNome,
                    'carga_horaria' => $chAnual,
                    'nota_final' => $notaFinal,
                    'situacao' => $situacaoDisc,
                    'ordem' => $ordemDisc++,
                ]
            );
        }

        return ['total' => $disciplinas->count(), 'reprovadas' => $reprovadas];
    }

    /**
     * Nota e situação de uma disciplina no histórico. A nota de corte vem do período letivo da turma
     * (nunca fixa no código) e o exame final, quando lançado, prevalece sobre a média do período.
     *
     * @return array{nota: ?float, situacao: string}
     */
    private function apurarDisciplina(Matricula $matricula, Disciplina $disciplina): array
    {
        $periodoLetivo = $matricula->periodoLetivo;
        $notaAprovacao = (float) ($periodoLetivo?->nota_aprovacao ?? 7.0);
        $notaAprovacaoPosExame = (float) ($periodoLetivo?->nota_aprovacao_pos_exame ?? 5.0);

        // Buscar nota final calculada/fechada
        $situacaoFinal = SituacaoFinalDisciplina::where('matricula_id', $matricula->id)
            ->where('disciplina_id', $disciplina->id)
            ->first();

        $notaFinal = null;
        if ($situacaoFinal && $situacaoFinal->media_final !== null) {
            $notaFinal = (float) $situacaoFinal->media_final;
        } elseif ($periodoLetivo) {
            // Tentar calcular via serviço se ainda não estiver gravado
            try {
                $calc = $this->fechamentoCicloService->calcularSituacaoFinal($matricula, $disciplina, $periodoLetivo);
                $notaFinal = $calc['media_final'] ?? null;
            } catch (\Throwable) {
                $notaFinal = null;
            }
        }

        $mediaPosExame = $situacaoFinal?->media_final_pos_exame;
        if ($mediaPosExame !== null) {
            $notaFinal = (float) $mediaPosExame;
        }

        $situacaoPosExame = $situacaoFinal?->situacao_final_pos_exame;

        $situacao = match (true) {
            $situacaoPosExame !== null => $situacaoPosExame === SituacaoFinal::APROVADO ? 'Aprovado' : 'Reprovado',
            $notaFinal === null => 'Pendente',
            // Média pós-exame sem a situação gravada: vale o corte do pós-exame do período.
            $mediaPosExame !== null => $notaFinal >= $notaAprovacaoPosExame ? 'Aprovado' : 'Reprovado',
            default => $notaFinal >= $notaAprovacao ? 'Aprovado' : 'Reprovado',
        };

        return ['nota' => $notaFinal, 'situacao' => $situacao];
    }

    /**
     * Resultado do ano letivo no histórico. Só a matrícula concluída recebe resultado final:
     * frequência abaixo de 75% reprova por faltas (LDB, art. 24, VI), qualquer que seja a nota; com a
     * frequência cumprida, até duas disciplinas reprovadas avançam com dependência e três ou mais retêm.
     */
    private function apurarSituacaoAno(?SituacaoMatricula $situacaoMatricula, float $frequenciaPercentual, int $disciplinasReprovadas): string
    {
        return match ($situacaoMatricula) {
            SituacaoMatricula::CANCELADA, SituacaoMatricula::TRANCADA, SituacaoMatricula::EVASAO => 'Transferido',
            SituacaoMatricula::CONCLUIDA => match (true) {
                $frequenciaPercentual < self::FREQUENCIA_MINIMA => 'Reprovado por Faltas',
                $disciplinasReprovadas === 0 => 'Aprovado',
                $disciplinasReprovadas <= self::MAX_DISCIPLINAS_DEPENDENCIA => 'Aprovado com Dependência',
                default => 'Reprovado',
            },
            default => 'Cursando',
        };
    }

    /**
     * Monta a matriz estruturada bidimensional para visualização e impressão do Histórico Escolar.
     *
     * @return array{
     *     anos: Collection<int, HistoricoEscolarAno>,
     *     areas: array<string, array<string, array<int, array{nota: ?float, conceito: ?string, ch: ?int, situacao: ?string}>>>,
     *     totais_anos: array<int, array{ch_total: int, dias: int, freq: float, situacao: string}>,
     *     estabelecimentos: array<int, array{ano: int, serie: string, escola: string, cidade_uf: string}>
     * }
     */
    public function montarMatrizTabular(HistoricoEscolar $historico): array
    {
        $anos = $historico->anos()->with(['disciplinas.disciplina'])->get();

        // 1. Mapeamento de Áreas -> Disciplinas -> Valores por Ano
        $areas = [];
        $totaisAnos = [];
        $estabelecimentos = [];

        foreach ($anos as $ano) {
            $totaisAnos[$ano->id] = [
                'ch_total' => (int) ($ano->carga_horaria_total ?: 0),
                'dias' => (int) ($ano->dias_letivos ?: 200),
                'freq' => (float) ($ano->frequencia_percentual ?: 100),
                'situacao' => $ano->situacao_ano ?? 'Aprovado',
            ];

            $estabelecimentos[] = [
                'ano' => $ano->ano_letivo,
                'serie' => $ano->serie_nome,
                'escola' => $ano->escola_nome,
                'cidade_uf' => trim("{$ano->escola_cidade}/{$ano->escola_uf}", '/'),
            ];

            foreach ($ano->disciplinas as $item) {
                $area = $item->area_conhecimento ?: 'Base Nacional Comum';
                $nomeDisc = $item->disciplina_nome;

                if (! isset($areas[$area])) {
                    $areas[$area] = [];
                }

                if (! isset($areas[$area][$nomeDisc])) {
                    $areas[$area][$nomeDisc] = [];
                }

                $areas[$area][$nomeDisc][$ano->id] = [
                    'nota' => $item->nota_final !== null ? (float) $item->nota_final : null,
                    'conceito' => $item->conceito,
                    'ch' => $item->carga_horaria,
                    'situacao' => $item->situacao,
                ];
            }
        }

        // Ordenar áreas de conhecimento no padrão clássico do MEC/BNCC
        $ordemPadraoAreas = [
            'Linguagens' => 1,
            'Linguagens e suas Tecnologias' => 2,
            'Matemática' => 3,
            'Matemática e suas Tecnologias' => 4,
            'Ciências da Natureza' => 5,
            'Ciências da Natureza e suas Tecnologias' => 6,
            'Ciências Humanas' => 7,
            'Ciências Humanas e Sociais Aplicadas' => 8,
            'Ensino Religioso' => 9,
            'Parte Diversificada' => 10,
            'Itinerários Formativos' => 11,
            'Base Nacional Comum' => 12,
        ];

        uksort($areas, function ($a, $b) use ($ordemPadraoAreas) {
            $ordemA = $ordemPadraoAreas[$a] ?? 99;
            $ordemB = $ordemPadraoAreas[$b] ?? 99;

            return $ordemA <=> $ordemB;
        });

        return [
            'anos' => $anos,
            'areas' => $areas,
            'totais_anos' => $totaisAnos,
            'estabelecimentos' => $estabelecimentos,
        ];
    }

    /**
     * Gera o arquivo PDF oficial do Histórico Escolar Multi-Ano formatado para impressão em A4 Landscape.
     */
    public function gerarPdf(HistoricoEscolar $historico): DomPdfInstance
    {
        $aluno = $historico->pessoa;
        $unidade = $historico->unidade ?? $aluno?->matriculas()->latest()->first()?->turma?->serie?->curso?->unidade;
        $instituicao = $unidade?->instituicaoEnsino;

        // Vínculos familiares (Pai e Mãe)
        $vinculoPai = TipoVinculo::whereIn('nome', ['Pai', 'pai'])->first();
        $vinculoMae = TipoVinculo::whereIn('nome', ['Mãe', 'mãe', 'Mae', 'mae'])->first();
        $nomePai = 'Não declarado';
        $nomeMae = 'Não declarada';

        if ($aluno) {
            if ($vinculoPai && $pai = $aluno->responsaveis()->wherePivot('tipo_vinculo_id', $vinculoPai->id)->first()) {
                $nomePai = $pai->nome;
            }
            if ($vinculoMae && $mae = $aluno->responsaveis()->wherePivot('tipo_vinculo_id', $vinculoMae->id)->first()) {
                $nomeMae = $mae->nome;
            }
        }

        // Representantes legais da unidade (Diretor e Secretário)
        $diretorNome = 'Diretor(a) Escolar';
        $secretarioNome = 'Secretário(a) Escolar';

        if ($unidade) {
            $diretor = $unidade->representantesLegais()
                ->where(function ($q) {
                    $q->where('cargo', 'like', '%diretor%')
                        ->orWhere('cargo', 'like', '%diretora%');
                })->first();

            $secretario = $unidade->representantesLegais()
                ->where(function ($q) {
                    $q->where('cargo', 'like', '%secretar%');
                })->first();

            if ($diretor) {
                $diretorNome = $diretor->nome;
            }
            if ($secretario) {
                $secretarioNome = $secretario->nome;
            }
        }

        // Matriz tabular
        $matriz = $this->montarMatrizTabular($historico);

        // QR Code de Autenticidade
        $urlValidacao = url('/validar-documento/'.$historico->codigo_autenticidade);
        $qrCodeDataUri = $this->qrCodeService->renderDataUri($urlValidacao);

        // Logo da escola
        $logoBase64 = $this->obterLogoBase64($unidade);

        $dadosView = [
            'historico' => $historico,
            'aluno' => $aluno,
            'unidade' => $unidade,
            'instituicao' => $instituicao,
            'nomePai' => $nomePai,
            'nomeMae' => $nomeMae,
            'diretorNome' => $diretorNome,
            'secretarioNome' => $secretarioNome,
            'matriz' => $matriz,
            'qrCodeDataUri' => $qrCodeDataUri,
            'urlValidacao' => $urlValidacao,
            'logoBase64' => $logoBase64,
            'dataEmissaoFormatada' => $historico->data_emissao
                ? Carbon::parse($historico->data_emissao)->translatedFormat('d \d\e F \d\e Y')
                : now()->translatedFormat('d \d\e F \d\e Y'),
        ];

        return Pdf::loadView('pdfs.historico_escolar', $dadosView)
            ->setPaper('a4', 'landscape');
    }

    /**
     * Retorna a logo da instituição em Base64.
     */
    protected function obterLogoBase64(?Unidade $unidade): ?string
    {
        $instituicao = $unidade?->instituicaoEnsino;
        $logoPath = $instituicao?->logo;

        if (! $logoPath) {
            return null;
        }

        try {
            $disk = config('filament.default_filesystem_disk', 'local');
            if (Storage::disk($disk)->exists($logoPath)) {
                $content = Storage::disk($disk)->get($logoPath);
                $mime = Storage::disk($disk)->mimeType($logoPath);

                return 'data:'.$mime.';base64,'.base64_encode($content);
            } elseif (Storage::disk('public')->exists($logoPath)) {
                $content = Storage::disk('public')->get($logoPath);
                $mime = Storage::disk('public')->mimeType($logoPath);

                return 'data:'.$mime.';base64,'.base64_encode($content);
            }
        } catch (\Throwable) {
            // Ignora falha de leitura
        }

        return null;
    }
}
