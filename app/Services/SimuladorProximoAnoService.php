<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StatusListaEspera;
use App\Enums\StatusRematricula;
use App\Models\CenarioProximoAno;
use App\Models\ListaEsperaMatricula;
use App\Models\Matricula;
use App\Models\PeriodoRematricula;
use App\Models\Rematricula;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Simulador do próximo ano letivo (Onda 76): com a rematrícula em andamento, a lista de espera, o risco de evasão e as turmas já
 * criadas para o período de destino, estima quantos alunos cada série terá, quantas turmas ela pede e se cada turma chega ao ponto de
 * equilíbrio. Dá para mudar o que se supõe (taxas, corte de risco, reajuste, abrir ou fechar turma, capacidade) e guardar os cenários
 * para a reunião.
 *
 * O que entra na conta, por série de DESTINO (a que a família escolheu na rematrícula; o sistema não tem "próxima série" automática):
 *  - rematrículas confirmadas: contam inteiras;
 *  - rematrículas iniciadas e ainda não confirmadas: pesam pela taxa de andamento, depois de tirar quem tem risco de evasão alto;
 *  - lista de espera: soma até o limite das vagas que sobram;
 *  - alunos que ainda nem iniciaram a rematrícula: não têm série de destino, então entram à parte ("sem série definida"), pela taxa
 *    de não iniciadas, e só no total.
 *
 * É uma ESTIMATIVA. A tela diz o que entrou na conta e rotula o resultado como projeção, não como compromisso.
 */
class SimuladorProximoAnoService
{
    /**
     * Os parâmetros com que o simulador abre.
     *
     * @return array{taxa_andamento: float, taxa_nao_iniciadas: float, corte_risco: int, incluir_espera: bool, reajuste_percentual: float, turmas: array<int, int>, capacidade: array<int, int>}
     */
    public function parametrosPadrao(): array
    {
        return $this->normalizar([]);
    }

    /**
     * A campanha de rematrícula aberta hoje ou, se não houver, a mais recente.
     */
    public function periodoPadrao(): ?PeriodoRematricula
    {
        return PeriodoRematricula::query()
            ->orderByRaw('case when is_ativo = 1 then 0 else 1 end')
            ->orderByDesc('data_inicio')
            ->first();
    }

    /**
     * Valores seguros: percentuais entre 0 e 100, número de turmas a partir de 0 (zero é fechar a série) e capacidade de pelo menos 1.
     *
     * @param  array<string, mixed>  $parametros
     * @return array{taxa_andamento: float, taxa_nao_iniciadas: float, corte_risco: int, incluir_espera: bool, reajuste_percentual: float, turmas: array<int, int>, capacidade: array<int, int>}
     */
    public function normalizar(array $parametros): array
    {
        $padroes = (array) config('simulador.padroes', []);
        $valor = fn (string $chave, mixed $reserva): mixed => $parametros[$chave] ?? $padroes[$chave] ?? $reserva;

        $turmas = [];
        $capacidade = [];

        foreach ((array) ($parametros['turmas'] ?? []) as $serieId => $quantidade) {
            if ($quantidade !== null && $quantidade !== '') {
                $turmas[(int) $serieId] = max(0, (int) $quantidade);
            }
        }

        foreach ((array) ($parametros['capacidade'] ?? []) as $serieId => $vagas) {
            if ($vagas !== null && $vagas !== '') {
                $capacidade[(int) $serieId] = max(1, (int) $vagas);
            }
        }

        return [
            'taxa_andamento' => max(0.0, min(100.0, (float) $valor('taxa_andamento', 90))),
            'taxa_nao_iniciadas' => max(0.0, min(100.0, (float) $valor('taxa_nao_iniciadas', 60))),
            'corte_risco' => max(0, min(100, (int) $valor('corte_risco', 60))),
            'incluir_espera' => (bool) $valor('incluir_espera', true),
            'reajuste_percentual' => max(-50.0, min(100.0, (float) $valor('reajuste_percentual', 0))),
            'turmas' => $turmas,
            'capacidade' => $capacidade,
        ];
    }

    /**
     * Sugestões de taxa tiradas da campanha anterior já encerrada: das rematrículas iniciadas, quantas terminaram confirmadas, e de
     * todos os alunos elegíveis, quantos renovaram.
     *
     * @return array{periodo: ?string, taxa_andamento: ?float, taxa_renovacao: ?float}
     */
    public function sugestoes(PeriodoRematricula $periodo): array
    {
        $anterior = PeriodoRematricula::query()
            ->whereKeyNot($periodo->getKey())
            ->whereDate('data_fim', '<', $periodo->data_inicio)
            ->orderByDesc('data_fim')
            ->first();

        if (! $anterior) {
            return ['periodo' => null, 'taxa_andamento' => null, 'taxa_renovacao' => null];
        }

        $confirmadas = Rematricula::query()->where('periodo_rematricula_id', $anterior->id)->where('status', StatusRematricula::Confirmada->value)->count();
        $perdidas = Rematricula::query()->where('periodo_rematricula_id', $anterior->id)->whereIn('status', $this->emAndamento())->count();
        $elegiveis = Matricula::query()->without('turma')->doPeriodo($anterior->periodo_letivo_origem_id)->whereNotIn('situacao', ['cancelada', 'pendente', 'reserva', 'evasao'])->count();

        return [
            'periodo' => $anterior->nome,
            'taxa_andamento' => $confirmadas + $perdidas > 0 ? round($confirmadas / ($confirmadas + $perdidas) * 100, 1) : null,
            'taxa_renovacao' => $elegiveis > 0 ? round($confirmadas / $elegiveis * 100, 1) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $parametros
     * @return array<string, mixed>
     */
    public function projetar(PeriodoRematricula $periodo, array $parametros = []): array
    {
        $p = $this->normalizar($parametros);
        $avisos = [];

        // 1. Rematrículas do período por série de destino.
        $rematriculas = Rematricula::query()
            ->where('periodo_rematricula_id', $periodo->id)
            ->with(['matriculaOrigem', 'turmaDestino'])
            ->get();

        $serieDe = fn (Rematricula $r): ?int => ($r->serie_destino_id ?? $r->turmaDestino?->serie_id) ? (int) ($r->serie_destino_id ?? $r->turmaDestino->serie_id) : null;
        $emRisco = fn (Rematricula $r): bool => (int) ($r->matriculaOrigem?->risco_evasao_score ?? 0) >= $p['corte_risco'] && $p['corte_risco'] > 0;
        $confirmadasPorSerie = [];
        $andamentoPorSerie = [];
        $riscoPorSerie = [];
        $semSerie = ['andamento' => 0, 'risco' => 0];
        $recusaram = 0;

        foreach ($rematriculas as $rematricula) {
            $serie = $serieDe($rematricula);

            if ($rematricula->status === StatusRematricula::Cancelada) {
                $recusaram++;
            } elseif ($rematricula->status === StatusRematricula::Confirmada) {
                $confirmadasPorSerie[$serie ?? 0] = ($confirmadasPorSerie[$serie ?? 0] ?? 0) + 1;
            } elseif ($emRisco($rematricula)) {
                $riscoPorSerie[$serie ?? 0] = ($riscoPorSerie[$serie ?? 0] ?? 0) + 1;
            } else {
                $andamentoPorSerie[$serie ?? 0] = ($andamentoPorSerie[$serie ?? 0] ?? 0) + 1;
            }
        }

        // Confirmada sem série (matrícula antiga, sem destino) não entra por série: vai ao total, como o que não tem destino.
        $confirmadasSemSerie = $confirmadasPorSerie[0] ?? 0;
        unset($confirmadasPorSerie[0]);
        $semSerie['andamento'] = $andamentoPorSerie[0] ?? 0;
        $semSerie['risco'] = $riscoPorSerie[0] ?? 0;
        unset($andamentoPorSerie[0], $riscoPorSerie[0]);

        // 2. Quem ainda nem iniciou: matrículas ativas na origem sem nenhuma rematrícula no período.
        $jaIniciaram = $rematriculas->pluck('matricula_origem_id')->filter()->all();
        $elegiveis = Matricula::query()->without('turma')->with('contrato')->doPeriodo($periodo->periodo_letivo_origem_id)->where('situacao', 'ativa')->get();
        $naoIniciadas = $elegiveis->reject(fn (Matricula $m): bool => in_array($m->id, $jaIniciaram, true));
        $naoIniciadasEmRisco = $naoIniciadas->filter(fn (Matricula $m): bool => $p['corte_risco'] > 0 && (int) ($m->risco_evasao_score ?? 0) >= $p['corte_risco'])->count();
        $naoIniciadasValidas = $naoIniciadas->count() - $naoIniciadasEmRisco;

        // 3. Lista de espera do período de destino, por série da turma.
        $esperaPorSerie = ListaEsperaMatricula::query()
            ->where('periodo_letivo_id', $periodo->periodo_letivo_destino_id)
            ->whereIn('status', [StatusListaEspera::Aguardando->value, StatusListaEspera::Notificado->value])
            ->with('turma')
            ->get()
            ->groupBy(fn (ListaEsperaMatricula $e): int => (int) ($e->turma?->serie_id ?? 0))
            ->map(fn (Collection $g): int => $g->count())
            ->all();
        unset($esperaPorSerie[0]);

        // 4. Turmas já criadas no período de destino, por série.
        $turmasDestino = Turma::query()->abertasParaMatricula()->where('periodo_letivo_id', $periodo->periodo_letivo_destino_id)->get()->groupBy('serie_id');

        // 5. Custo médio de uma turma por série (o planejado, digitado na turma), no destino ou na origem.
        $custoPorSerie = Turma::query()
            ->whereIn('periodo_letivo_id', array_filter([$periodo->periodo_letivo_destino_id, $periodo->periodo_letivo_origem_id]))
            ->get(['serie_id', 'custo_docente_mensal', 'custo_operacional_rateado'])
            ->groupBy('serie_id')
            ->map(fn (Collection $g): float => (float) $g->map(fn (Turma $t): float => (float) $t->custo_docente_mensal + (float) $t->custo_operacional_rateado)->filter(fn (float $v): bool => $v > 0)->avg());

        // 6. Preço anual: o da tabela do período e, na falta dele, a média dos contratos atuais.
        $contratos = $elegiveis->map(fn (Matricula $m): float => (float) ($m->contrato?->valor_total ?? 0))->filter(fn (float $v): bool => $v > 0);
        $mediaContratos = $contratos->isEmpty() ? 0.0 : (float) $contratos->avg();
        $precos = $periodo->precos()->pluck('valor_total', 'serie_id')->map(fn ($v): float => (float) $v)->all();
        $fatorReajuste = 1 + $p['reajuste_percentual'] / 100;

        // 7. Uma linha por série de destino conhecida.
        $ids = collect(array_keys($confirmadasPorSerie))
            ->merge(array_keys($andamentoPorSerie))
            ->merge(array_keys($riscoPorSerie))
            ->merge(array_keys($esperaPorSerie))
            ->merge($turmasDestino->keys()->all())
            ->merge(array_keys($p['turmas']))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $series = Serie::query()->with('curso')->whereIn('id', $ids->all())->get()->keyBy('id');
        $linhas = [];

        foreach ($ids as $serieId) {
            $serie = $series->get($serieId);
            $existentes = $turmasDestino->get($serieId, collect());
            $confirmadas = $confirmadasPorSerie[$serieId] ?? 0;
            $andamento = $andamentoPorSerie[$serieId] ?? 0;
            $risco = $riscoPorSerie[$serieId] ?? 0;
            $espera = $esperaPorSerie[$serieId] ?? 0;
            $ponderado = round($andamento * $p['taxa_andamento'] / 100, 2);
            $demandaBase = $confirmadas + $ponderado;

            $mediaVagas = $existentes->isEmpty() ? 0 : (int) round((float) $existentes->avg(fn (Turma $t): int => (int) ($t->vagas_maximas ?? 0)));
            $capacidadePorTurma = $p['capacidade'][$serieId] ?? ($mediaVagas > 0 ? $mediaVagas : (int) config('simulador.capacidade_padrao', 30));
            $quantidadeTurmas = array_key_exists($serieId, $p['turmas']) ? $p['turmas'][$serieId] : $existentes->count();
            $vagas = array_key_exists($serieId, $p['capacidade']) || array_key_exists($serieId, $p['turmas'])
                ? $quantidadeTurmas * $capacidadePorTurma
                : (int) $existentes->sum(fn (Turma $t): int => (int) ($t->vagas_maximas ?? 0));

            $sobram = max(0.0, $vagas - $demandaBase);
            $esperaIncluida = $p['incluir_espera'] ? min((float) $espera, $sobram) : 0.0;
            $projecao = round($demandaBase + $esperaIncluida, 2);

            $precoAnual = ($precos[$serieId] ?? $mediaContratos) * $fatorReajuste;
            $ticket = $precoAnual / 12;
            $custoTurma = (float) ($custoPorSerie->get($serieId) ?? 0);
            $equilibrio = $ticket > 0 && $custoTurma > 0 ? (int) ceil($custoTurma / $ticket) : null;
            $porTurma = $quantidadeTurmas > 0 ? round($projecao / $quantidadeTurmas, 1) : null;

            $linhas[] = [
                'serie_id' => $serieId,
                'serie' => $serie ? trim(($serie->curso?->nome ? $serie->curso->nome.' — ' : '').$serie->nome) : "Série {$serieId}",
                'confirmadas' => $confirmadas,
                'em_andamento' => $andamento,
                'em_andamento_ponderado' => $ponderado,
                'em_risco' => $risco,
                'espera' => $espera,
                'espera_incluida' => round($esperaIncluida, 2),
                'projecao' => $projecao,
                'turmas' => $quantidadeTurmas,
                'turmas_atuais' => $existentes->count(),
                'capacidade_por_turma' => $capacidadePorTurma,
                'vagas' => $vagas,
                'vagas_livres' => round(max(0.0, $vagas - $projecao), 2),
                'excesso' => round(max(0.0, $projecao - $vagas), 2),
                'alunos_por_turma' => $porTurma,
                'preco_anual' => round($precoAnual, 2),
                'preco_origem' => isset($precos[$serieId]) ? 'tabela' : ($mediaContratos > 0 ? 'media' : 'sem_preco'),
                'receita_mensal' => round($projecao * $ticket, 2),
                'custo_turma' => round($custoTurma, 2),
                'ponto_equilibrio' => $equilibrio,
                'espera_maior_que_vagas' => $espera > $sobram && $espera > 0,
                'situacao' => match (true) {
                    $quantidadeTurmas === 0 && $projecao > 0 => 'sem_turma',
                    $projecao > $vagas => 'acima_da_capacidade',
                    $equilibrio !== null && $porTurma !== null && $porTurma < $equilibrio => 'abaixo_do_equilibrio',
                    default => 'ok',
                },
            ];
        }

        usort($linhas, fn (array $a, array $b): int => strcmp($a['serie'], $b['serie']));

        // 8. Totais.
        $projecaoSemSerie = round($confirmadasSemSerie + $semSerie['andamento'] * $p['taxa_andamento'] / 100 + $naoIniciadasValidas * $p['taxa_nao_iniciadas'] / 100, 2);
        $projecaoSeries = round(array_sum(array_column($linhas, 'projecao')), 2);
        $receitaSemSerie = round($projecaoSemSerie * ($mediaContratos * $fatorReajuste) / 12, 2);
        $receitaAtual = round((float) $contratos->sum() / 12, 2);
        $receitaPrevista = round(array_sum(array_column($linhas, 'receita_mensal')) + $receitaSemSerie, 2);

        if ($mediaContratos <= 0 && $precos === []) {
            $avisos[] = 'Sem tabela de preços do período e sem contratos atuais: a receita prevista não pôde ser calculada.';
        }

        if ($rematriculas->isEmpty() && $elegiveis->isEmpty()) {
            $avisos[] = 'Não há alunos ativos no período de origem nem rematrículas na campanha: a projeção está vazia.';
        }

        return [
            'periodo' => ['id' => $periodo->id, 'nome' => $periodo->nome, 'data_fim' => $periodo->data_fim],
            'parametros' => $p,
            'linhas' => $linhas,
            'sem_serie' => [
                'confirmadas' => $confirmadasSemSerie,
                'em_andamento' => $semSerie['andamento'],
                'em_risco' => $semSerie['risco'],
                'nao_iniciadas' => $naoIniciadas->count(),
                'nao_iniciadas_em_risco' => $naoIniciadasEmRisco,
                'projecao' => $projecaoSemSerie,
            ],
            'totais' => [
                'elegiveis' => $elegiveis->count(),
                'confirmadas' => array_sum($confirmadasPorSerie) + $confirmadasSemSerie,
                'em_andamento' => array_sum($andamentoPorSerie) + $semSerie['andamento'],
                'nao_iniciadas' => $naoIniciadas->count(),
                'recusaram' => $recusaram,
                'projecao' => round($projecaoSeries + $projecaoSemSerie, 2),
                'turmas' => array_sum(array_column($linhas, 'turmas')),
                'vagas' => array_sum(array_column($linhas, 'vagas')),
                'receita_mensal' => $receitaPrevista,
                'receita_atual_mensal' => $receitaAtual,
                'variacao_receita' => round($receitaPrevista - $receitaAtual, 2),
                'series_com_problema' => count(array_filter($linhas, fn (array $l): bool => $l['situacao'] !== 'ok' || $l['espera_maior_que_vagas'])),
            ],
            'avisos' => $avisos,
        ];
    }

    /**
     * Guarda o cenário: os parâmetros e a fotografia do resultado de hoje.
     *
     * @param  array<string, mixed>  $parametros
     *
     * @throws \DomainException
     */
    public function salvar(PeriodoRematricula $periodo, string $nome, array $parametros, ?User $por = null): CenarioProximoAno
    {
        $nome = trim($nome);

        if ($nome === '') {
            throw new \DomainException('Dê um nome ao cenário (ex.: Conservador).');
        }

        $resultado = $this->projetar($periodo, $parametros);

        return CenarioProximoAno::create([
            'nome' => mb_substr($nome, 0, 120),
            'periodo_rematricula_id' => $periodo->id,
            'parametros' => $resultado['parametros'],
            'resultado' => json_decode((string) json_encode($resultado, JSON_UNESCAPED_UNICODE), true),
            'criado_por_user_id' => $por?->id,
        ]);
    }

    /**
     * Os cenários lado a lado: o que importa para decidir, em uma linha por cenário.
     *
     * @param  Collection<int, CenarioProximoAno>  $cenarios
     * @return list<array<string, mixed>>
     */
    public function comparar(Collection $cenarios): array
    {
        return $cenarios->map(function (CenarioProximoAno $cenario): array {
            $r = (array) $cenario->resultado;
            $totais = (array) ($r['totais'] ?? []);
            $p = (array) ($cenario->parametros ?? []);

            return [
                'id' => $cenario->id,
                'nome' => $cenario->nome,
                'criado_em' => $cenario->created_at,
                'projecao' => (float) ($totais['projecao'] ?? 0),
                'turmas' => (int) ($totais['turmas'] ?? 0),
                'vagas' => (int) ($totais['vagas'] ?? 0),
                'receita_mensal' => (float) ($totais['receita_mensal'] ?? 0),
                'variacao_receita' => (float) ($totais['variacao_receita'] ?? 0),
                'series_com_problema' => (int) ($totais['series_com_problema'] ?? 0),
                'taxa_andamento' => (float) ($p['taxa_andamento'] ?? 0),
                'taxa_nao_iniciadas' => (float) ($p['taxa_nao_iniciadas'] ?? 0),
                'reajuste_percentual' => (float) ($p['reajuste_percentual'] ?? 0),
            ];
        })->values()->all();
    }

    /**
     * Os problemas da projeção padrão que a Minha Mesa mostra: série sem turma, acima da capacidade, abaixo do equilíbrio ou com
     * lista de espera maior que as vagas que sobram. O prazo é o fim da campanha de rematrícula.
     *
     * @return list<array{serie: string, problema: string, detalhe: string, urgente: bool}>
     */
    public function alertas(?PeriodoRematricula $periodo = null, ?CarbonInterface $hoje = null): array
    {
        $periodo ??= $this->periodoPadrao();

        if (! $periodo) {
            return [];
        }

        $hoje = CarbonImmutable::parse($hoje ?? today());
        $limite = $hoje->addDays(max(0, (int) config('simulador.alerta_prazo_dias', 15)));
        $urgente = $periodo->data_fim !== null && CarbonImmutable::parse($periodo->data_fim)->lte($limite);
        $alertas = [];

        foreach ($this->projetar($periodo)['linhas'] as $linha) {
            $texto = match ($linha['situacao']) {
                'sem_turma' => ['Demanda sem turma', 'Projeção de '.number_format($linha['projecao'], 1, ',', '.').' aluno(s) e nenhuma turma aberta.'],
                'acima_da_capacidade' => ['Acima da capacidade', number_format($linha['projecao'], 1, ',', '.').' aluno(s) para '.$linha['vagas'].' vaga(s).'],
                'abaixo_do_equilibrio' => ['Abaixo do ponto de equilíbrio', number_format((float) $linha['alunos_por_turma'], 1, ',', '.').' aluno(s) por turma; o equilíbrio pede '.$linha['ponto_equilibrio'].'.'],
                default => null,
            };

            if ($texto === null && $linha['espera_maior_que_vagas']) {
                $texto = ['Lista de espera maior que as vagas', $linha['espera'].' na espera para '.number_format($linha['vagas_livres'], 1, ',', '.').' vaga(s) que sobram.'];
            }

            if ($texto !== null) {
                $alertas[] = ['serie' => $linha['serie'], 'problema' => $texto[0], 'detalhe' => $texto[1], 'urgente' => $urgente];
            }
        }

        return $alertas;
    }

    /**
     * @return list<string>
     */
    private function emAndamento(): array
    {
        return [StatusRematricula::Iniciada->value, StatusRematricula::DadosConfirmados->value, StatusRematricula::AguardandoAssinatura->value];
    }
}
