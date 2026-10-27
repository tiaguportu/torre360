<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StatusFatura;
use App\Enums\StatusTurma;
use App\Models\ApuracaoHoraAula;
use App\Models\CronogramaAula;
use App\Models\Fatura;
use App\Models\Matricula;
use App\Models\TransacaoBancaria;
use App\Models\Turma;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Resultado REALIZADO de cada turma no mês (Onda 77), ao lado do planejado que a Controladoria de Turmas já calcula (mensalidade base
 * e custos digitados na turma). Aqui tudo vem do que aconteceu:
 *
 *  - receita: faturas com vencimento no mês (já líquidas de bolsa e desconto), o quanto foi pago e o quanto está em atraso;
 *  - custo docente: a apuração de hora-aula do professor no mês, dividida entre as turmas pela proporção das aulas do cronograma;
 *  - custo direto: saídas bancárias do mês, classificadas como despesa, em centro de custo vinculado à turma (ou ao curso, dividido
 *    entre as turmas do curso por aluno);
 *  - custo indireto: as demais saídas de despesa (e o custo docente sem aulas no cronograma), rateadas por um critério escolhido.
 *
 * O rateio é uma ESCOLHA, não um fato: a tela mostra o critério ao lado do número. Contabilidade gerencial em regime de caixa para as
 * despesas e de competência para a receita; a contabilidade oficial segue com o escritório (Onda 73).
 */
class RentabilidadeTurmaService
{
    public const CRITERIOS = [
        'aluno' => 'Por aluno',
        'turma' => 'Igual para cada turma',
        'hora_aula' => 'Por hora-aula (aulas no mês)',
    ];

    private const MESES = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

    public function __construct(private readonly ControladoriaTurmaService $planejado) {}

    /**
     * "Setembro/2026": o nome do mês em português, sem depender do idioma configurado no servidor.
     */
    public static function rotuloDoMes(CarbonInterface $mes): string
    {
        return self::MESES[$mes->month].'/'.$mes->year;
    }

    /**
     * @return array{
     *     ano: int, mes: int, criterio: string, criterio_nome: string, linhas: list<array<string, mixed>>, totais: array<string, mixed>,
     *     indireto_total: float, fora_do_resultado: float, sem_classificacao: array{quantidade: int, valor: float},
     *     docente_sem_turma: float
     * }
     */
    public function calcular(int $ano, int $mes, string $criterio = 'aluno', ?int $unidadeId = null, bool $comPlanejado = false): array
    {
        $criterio = array_key_exists($criterio, self::CRITERIOS) ? $criterio : 'aluno';
        $inicio = CarbonImmutable::create($ano, $mes, 1)->startOfDay();
        $fim = $inicio->endOfMonth()->startOfDay();

        $turmas = $this->turmasDoMes($inicio, $fim);
        $ids = $turmas->pluck('id')->all();

        $receita = $this->receitaPorTurma($inicio, $fim, $ids);
        $alunos = $this->alunosPorTurma($inicio, $fim, $ids);
        $aulas = $this->aulasPorTurma($inicio, $fim, $ids);
        [$docente, $docenteSemTurma] = $this->custoDocente($ano, $mes, $inicio, $fim, $ids);
        $despesas = $this->despesas($inicio, $fim, $turmas, $alunos);

        $pesos = [];

        foreach ($ids as $id) {
            $pesos[$id] = match ($criterio) {
                'turma' => 1.0,
                'hora_aula' => (float) ($aulas[$id] ?? 0),
                default => (float) ($alunos[$id] ?? 0),
            };
        }

        $pool = $despesas['indireto'] + $docenteSemTurma;
        $somaPesos = array_sum($pesos);
        $linhas = [];

        foreach ($turmas as $turma) {
            $id = $turma->id;
            $faturado = round($receita[$id]['faturado'] ?? 0.0, 2);
            $recebido = round($receita[$id]['recebido'] ?? 0.0, 2);
            $atraso = round($receita[$id]['atraso'] ?? 0.0, 2);
            $custoDocente = round($docente[$id] ?? 0.0, 2);
            $custoDireto = round($despesas['direto'][$id] ?? 0.0, 2);
            $custoIndireto = $somaPesos > 0 ? round($pool * $pesos[$id] / $somaPesos, 2) : 0.0;
            $custoTotal = round($custoDocente + $custoDireto + $custoIndireto, 2);
            $resultado = round($faturado - $custoTotal, 2);
            $quantidadeAlunos = (int) ($alunos[$id] ?? 0);

            $linha = [
                'turma_id' => $id,
                'turma' => $turma->nome ?: ($turma->codigo ?: "Turma {$id}"),
                'serie_id' => $turma->serie_id,
                'serie' => $turma->serie?->nome ?? '—',
                'curso_id' => $turma->serie?->curso_id,
                'curso' => $turma->serie?->curso?->nome ?? '—',
                'unidade_id' => $turma->serie?->curso?->unidade_id,
                'unidade' => $turma->serie?->curso?->unidade?->nome ?? '—',
                'alunos' => $quantidadeAlunos,
                'faturado' => $faturado,
                'recebido' => $recebido,
                'em_atraso' => $atraso,
                'custo_docente' => $custoDocente,
                'custo_direto' => $custoDireto,
                'custo_indireto' => $custoIndireto,
                'custo_total' => $custoTotal,
                'resultado' => $resultado,
                'margem' => $faturado > 0 ? round($resultado / $faturado * 100, 1) : null,
                'custo_por_aluno' => $quantidadeAlunos > 0 ? round($custoTotal / $quantidadeAlunos, 2) : null,
                'resultado_por_aluno' => $quantidadeAlunos > 0 ? round($resultado / $quantidadeAlunos, 2) : null,
                'situacao' => match (true) {
                    $faturado <= 0 && $custoTotal <= 0 => 'sem_movimento',
                    $resultado < 0 => 'negativa',
                    default => 'positiva',
                },
            ];

            if ($comPlanejado) {
                $metricas = $this->planejado->calcularMetricas($turma);
                $linha['planejado_resultado'] = round((float) $metricas['resultado_mensal'], 2);
                $linha['planejado_margem'] = (float) $metricas['margem_percentual'];
            }

            $linhas[] = $linha;
        }

        if ($unidadeId !== null) {
            $linhas = array_values(array_filter($linhas, fn (array $l): bool => $l['unidade_id'] === $unidadeId));
        }

        usort($linhas, fn (array $a, array $b): int => $a['resultado'] <=> $b['resultado']);

        return [
            'ano' => $ano,
            'mes' => $mes,
            'criterio' => $criterio,
            'criterio_nome' => self::CRITERIOS[$criterio],
            'linhas' => $linhas,
            'totais' => $this->somar($linhas),
            'indireto_total' => round($pool, 2),
            'fora_do_resultado' => round($despesas['fora'], 2),
            'sem_classificacao' => $despesas['sem_classificacao'],
            'docente_sem_turma' => round($docenteSemTurma, 2),
        ];
    }

    /**
     * O mesmo cálculo comparado ao mês anterior e ao mesmo mês do ano anterior: cada linha ganha o resultado e a margem de lá.
     *
     * @return array<string, mixed>
     */
    public function comparar(int $ano, int $mes, string $criterio = 'aluno', ?int $unidadeId = null): array
    {
        $atual = $this->calcular($ano, $mes, $criterio, $unidadeId, comPlanejado: true);
        $anterior = CarbonImmutable::create($ano, $mes, 1)->subMonthNoOverflow();
        $mesAnterior = $this->calcular($anterior->year, $anterior->month, $criterio, $unidadeId);
        $anoAnterior = $this->calcular($ano - 1, $mes, $criterio, $unidadeId);

        $indexar = fn (array $r): Collection => collect($r['linhas'])->keyBy('turma_id');
        $deMes = $indexar($mesAnterior);
        $deAno = $indexar($anoAnterior);

        foreach ($atual['linhas'] as &$linha) {
            $linha['mes_anterior'] = isset($deMes[$linha['turma_id']]) ? ['resultado' => $deMes[$linha['turma_id']]['resultado'], 'margem' => $deMes[$linha['turma_id']]['margem']] : null;
            $linha['ano_anterior'] = isset($deAno[$linha['turma_id']]) ? ['resultado' => $deAno[$linha['turma_id']]['resultado'], 'margem' => $deAno[$linha['turma_id']]['margem']] : null;
        }

        unset($linha);

        $atual['totais_mes_anterior'] = $mesAnterior['totais'];
        $atual['totais_ano_anterior'] = $anoAnterior['totais'];

        return $atual;
    }

    /**
     * Soma as linhas por série, curso ou unidade.
     *
     * @param  list<array<string, mixed>>  $linhas
     * @return list<array<string, mixed>>
     */
    public function agrupar(array $linhas, string $por): array
    {
        $campo = match ($por) {
            'serie' => ['serie_id', 'serie'],
            'curso' => ['curso_id', 'curso'],
            'unidade' => ['unidade_id', 'unidade'],
            default => null,
        };

        if ($campo === null) {
            return $linhas;
        }

        return collect($linhas)
            ->groupBy(fn (array $l): string => (string) $l[$campo[0]])
            ->map(function (Collection $grupo) use ($campo): array {
                $soma = $this->somar($grupo->all());

                return $soma + ['nome' => (string) $grupo->first()[$campo[1]], 'turmas' => $grupo->count()];
            })
            ->sortBy('resultado')
            ->values()
            ->all();
    }

    /**
     * Turmas com resultado negativo (e receita) em todos os últimos N meses completos: a pergunta da Mesa.
     *
     * @return list<array{turma_id: int, turma: string, resultados: list<float>, margens: list<?float>}>
     */
    public function comResultadoNegativoHa(int $meses, ?CarbonInterface $hoje = null, string $criterio = 'aluno'): array
    {
        $meses = max(1, $meses);
        $referencia = CarbonImmutable::parse($hoje ?? today())->startOfMonth();
        $porTurma = [];

        for ($i = 1; $i <= $meses; $i++) {
            $mes = $referencia->subMonthsNoOverflow($i);
            $resultado = $this->calcular($mes->year, $mes->month, $criterio);

            foreach ($resultado['linhas'] as $linha) {
                $porTurma[$linha['turma_id']]['turma'] = $linha['turma'];
                $porTurma[$linha['turma_id']]['resultados'][] = $linha['situacao'] === 'negativa' && $linha['faturado'] > 0 ? $linha['resultado'] : null;
                $porTurma[$linha['turma_id']]['margens'][] = $linha['margem'];
            }
        }

        $lista = [];

        foreach ($porTurma as $id => $dados) {
            if (count($dados['resultados']) === $meses && ! in_array(null, $dados['resultados'], true)) {
                $lista[] = ['turma_id' => (int) $id, 'turma' => $dados['turma'], 'resultados' => $dados['resultados'], 'margens' => $dados['margens']];
            }
        }

        return $lista;
    }

    /**
     * @param  list<array<string, mixed>>  $linhas
     * @return array<string, mixed>
     */
    private function somar(array $linhas): array
    {
        $soma = ['alunos' => 0, 'faturado' => 0.0, 'recebido' => 0.0, 'em_atraso' => 0.0, 'custo_docente' => 0.0, 'custo_direto' => 0.0, 'custo_indireto' => 0.0, 'custo_total' => 0.0, 'resultado' => 0.0];

        foreach ($linhas as $linha) {
            foreach (array_keys($soma) as $chave) {
                $soma[$chave] += $linha[$chave];
            }
        }

        foreach (array_keys($soma) as $chave) {
            $soma[$chave] = $chave === 'alunos' ? (int) $soma[$chave] : round($soma[$chave], 2);
        }

        $soma['margem'] = $soma['faturado'] > 0 ? round($soma['resultado'] / $soma['faturado'] * 100, 1) : null;
        $soma['custo_por_aluno'] = $soma['alunos'] > 0 ? round($soma['custo_total'] / $soma['alunos'], 2) : null;
        $soma['resultado_por_aluno'] = $soma['alunos'] > 0 ? round($soma['resultado'] / $soma['alunos'], 2) : null;

        return $soma;
    }

    /**
     * Turmas cujo período letivo toca o mês e que não foram canceladas nem estão só planejadas.
     *
     * @return Collection<int, Turma>
     */
    private function turmasDoMes(CarbonImmutable $inicio, CarbonImmutable $fim): Collection
    {
        return Turma::query()
            ->with(['serie.curso.unidade', 'turno'])
            ->whereIn('status', [StatusTurma::Ativa->value, StatusTurma::Concluida->value])
            ->whereHas('periodoLetivo', fn ($q) => $q->whereDate('data_inicio', '<=', $fim->toDateString())->whereDate('data_fim', '>=', $inicio->toDateString()))
            ->orderBy('nome')
            ->get();
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{faturado: float, recebido: float, atraso: float}>
     */
    private function receitaPorTurma(CarbonImmutable $inicio, CarbonImmutable $fim, array $ids): array
    {
        $faturas = Fatura::query()
            ->where('status', '!=', StatusFatura::Cancelado->value)
            ->whereDate('vencimento', '>=', $inicio->toDateString())
            ->whereDate('vencimento', '<=', $fim->toDateString())
            ->whereHas('contrato.matricula', fn ($q) => $q->whereIn('turma_id', $ids))
            ->with(['itens', 'transacoes', 'contrato.matricula'])
            ->get();

        $receita = [];

        foreach ($faturas as $fatura) {
            $turmaId = $fatura->contrato?->matricula?->turma_id;

            if ($turmaId === null) {
                continue;
            }

            $valor = (float) $fatura->valor;
            $pago = min($valor, (float) $fatura->valor_pago);
            $restante = max(0.0, $valor - $pago);

            $receita[$turmaId]['faturado'] = ($receita[$turmaId]['faturado'] ?? 0.0) + $valor;
            $receita[$turmaId]['recebido'] = ($receita[$turmaId]['recebido'] ?? 0.0) + $pago;
            $receita[$turmaId]['atraso'] = ($receita[$turmaId]['atraso'] ?? 0.0) + ($fatura->vencimento->lt(today()) ? $restante : 0.0);
        }

        return $receita;
    }

    /**
     * Matrículas ativas em algum momento do mês: ativadas até o fim dele e não desativadas antes do início.
     *
     * @param  list<int>  $ids
     * @return array<int, int>
     */
    private function alunosPorTurma(CarbonImmutable $inicio, CarbonImmutable $fim, array $ids): array
    {
        return Matricula::query()
            ->without('turma')
            ->whereIn('turma_id', $ids)
            ->whereNotIn('situacao', ['pendente', 'reserva'])
            ->where(fn ($q) => $q->whereNull('data_ativacao')->orWhereDate('data_ativacao', '<=', $fim->toDateString()))
            ->where(fn ($q) => $q->whereNull('data_desativacao')->orWhereDate('data_desativacao', '>=', $inicio->toDateString()))
            ->selectRaw('turma_id, count(*) as total')
            ->groupBy('turma_id')
            ->pluck('total', 'turma_id')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, int> aulas do cronograma por turma no mês
     */
    private function aulasPorTurma(CarbonImmutable $inicio, CarbonImmutable $fim, array $ids): array
    {
        return CronogramaAula::query()
            ->whereIn('turma_id', $ids)
            ->whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])
            ->selectRaw('turma_id, count(*) as total')
            ->groupBy('turma_id')
            ->pluck('total', 'turma_id')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }

    /**
     * Custo docente por turma: a apuração do professor no mês, dividida pela proporção das aulas dele em cada turma. O que não tem
     * aula no cronograma não tem como ser dividido e volta como "sem turma" (vai ao pool indireto).
     *
     * @param  list<int>  $ids
     * @return array{0: array<int, float>, 1: float}
     */
    private function custoDocente(int $ano, int $mes, CarbonImmutable $inicio, CarbonImmutable $fim, array $ids): array
    {
        $apuracoes = ApuracaoHoraAula::query()->where('ano', $ano)->where('mes', $mes)->get(['pessoa_id', 'valor_total_bruto']);

        $aulasDoProfessor = CronogramaAula::query()
            ->whereIn('pessoa_id', $apuracoes->pluck('pessoa_id')->all())
            ->whereNotNull('turma_id')
            ->whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])
            ->selectRaw('pessoa_id, turma_id, count(*) as total')
            ->groupBy('pessoa_id', 'turma_id')
            ->get()
            ->groupBy('pessoa_id');

        $porTurma = [];
        $semTurma = 0.0;

        foreach ($apuracoes as $apuracao) {
            $valor = (float) $apuracao->valor_total_bruto;
            $aulas = $aulasDoProfessor->get($apuracao->pessoa_id, collect());
            $total = (float) $aulas->sum('total');

            if ($total <= 0) {
                $semTurma += $valor;

                continue;
            }

            foreach ($aulas as $aula) {
                $parte = $valor * ((float) $aula->total / $total);

                if (in_array((int) $aula->turma_id, $ids, true)) {
                    $porTurma[(int) $aula->turma_id] = ($porTurma[(int) $aula->turma_id] ?? 0.0) + $parte;
                } else {
                    // Aula em turma fora da lista (cancelada, de outro período): o custo dela não pertence a nenhuma turma daqui.
                    $semTurma += $parte;
                }
            }
        }

        return [$porTurma, $semTurma];
    }

    /**
     * Saídas bancárias do mês classificadas como despesa, separadas em direto (por turma), indireto (pool), fora do resultado das
     * turmas e sem classificação (que não entram na conta: pode ser transferência).
     *
     * @param  Collection<int, Turma>  $turmas
     * @param  array<int, int>  $alunos
     * @return array{direto: array<int, float>, indireto: float, fora: float, sem_classificacao: array{quantidade: int, valor: float}}
     */
    private function despesas(CarbonImmutable $inicio, CarbonImmutable $fim, Collection $turmas, array $alunos): array
    {
        $saidas = TransacaoBancaria::query()
            ->where('tipo', 'saida')
            ->whereDate('data_transacao', '>=', $inicio->toDateString())
            ->whereDate('data_transacao', '<=', $fim->toDateString())
            ->with(['planoConta', 'centroCusto'])
            ->get();

        $direto = [];
        $indireto = 0.0;
        $fora = 0.0;
        $semClassificacao = ['quantidade' => 0, 'valor' => 0.0];
        $turmasPorCurso = $turmas->groupBy(fn (Turma $t): int => (int) $t->serie?->curso_id);

        foreach ($saidas as $saida) {
            $valor = (float) $saida->valor;

            if ($saida->planoConta === null) {
                $semClassificacao['quantidade']++;
                $semClassificacao['valor'] += $valor;

                continue;
            }

            if ($saida->planoConta->tipo !== 'despesa') {
                continue;
            }

            $centro = $saida->centroCusto;

            if ($centro?->fora_do_resultado_das_turmas) {
                $fora += $valor;
            } elseif ($centro?->turma_id !== null && $turmas->contains('id', $centro->turma_id)) {
                $direto[$centro->turma_id] = ($direto[$centro->turma_id] ?? 0.0) + $valor;
            } elseif ($centro?->curso_id !== null && $turmasPorCurso->has($centro->curso_id)) {
                $doCurso = $turmasPorCurso->get($centro->curso_id);
                $soma = (float) $doCurso->sum(fn (Turma $t): int => $alunos[$t->id] ?? 0);

                foreach ($doCurso as $turma) {
                    $parte = $soma > 0 ? ($alunos[$turma->id] ?? 0) / $soma : 1 / $doCurso->count();
                    $direto[$turma->id] = ($direto[$turma->id] ?? 0.0) + $valor * $parte;
                }
            } else {
                $indireto += $valor;
            }
        }

        $semClassificacao['valor'] = round($semClassificacao['valor'], 2);

        return ['direto' => $direto, 'indireto' => $indireto, 'fora' => $fora, 'sem_classificacao' => $semClassificacao];
    }
}
