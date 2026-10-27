<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StatusContaPagar;
use App\Enums\StatusFatura;
use App\Models\ContaPagar;
use App\Models\Fatura;
use App\Models\TransacaoBancaria;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Fluxo de caixa projetado (Onda 78): o que vem pela frente, dia a dia. Parte do saldo das transações bancárias lançadas e soma o
 * que se espera receber (faturas abertas) menos o que se espera pagar (contas a pagar abertas e despesas recorrentes que ainda não
 * viraram conta).
 *
 * É uma estimativa, e só vale se o banco estiver conciliado: saldo inicial errado estraga toda a projeção, por isso o resultado traz a
 * data do último lançamento bancário e quantas transações estão sem conciliar. Os três cenários mudam só a parte do recebimento:
 *  - esperado: o que vence é multiplicado pela taxa de recebimento dos últimos meses (o que costuma ser pago); o que já venceu e
 *    continua aberto entra com uma fração dela;
 *  - otimista: tudo que está aberto é recebido;
 *  - conservador: nenhuma fatura aberta é recebida (só entram as saídas).
 */
class FluxoProjetadoService
{
    public const CENARIOS = [
        'esperado' => 'Média histórica de recebimento',
        'otimista' => 'Receber tudo que está aberto',
        'conservador' => 'Não receber nenhuma fatura aberta',
    ];

    public function __construct(private readonly ContaRecorrenteService $recorrentes) {}

    /**
     * @return array{
     *     cenario: string, cenario_nome: string, inicio: CarbonImmutable, fim: CarbonImmutable, dias: int, saldo_inicial: float,
     *     taxa: ?float, taxa_origem: ?string, entradas: float, saidas: float, saldo_final: float, menor_saldo: float,
     *     data_menor_saldo: CarbonImmutable, primeira_data_negativa: ?CarbonImmutable,
     *     semanas: list<array{inicio: CarbonImmutable, fim: CarbonImmutable, entradas: float, saidas: float, saldo_final: float, negativa: bool}>,
     *     origem: array{faturas: array{quantidade: int, bruto: float, ponderado: float}, contas: array{quantidade: int, valor: float}, recorrentes: array{quantidade: int, valor: float}},
     *     conciliacao: array{ultimo_lancamento: ?CarbonImmutable, ultima_conciliada: ?CarbonImmutable, nao_conciliadas: int}
     * }
     */
    public function projetar(?CarbonInterface $inicio = null, ?int $dias = null, string $cenario = 'esperado'): array
    {
        $cenario = array_key_exists($cenario, self::CENARIOS) ? $cenario : 'esperado';
        $inicio = CarbonImmutable::parse($inicio ?? today())->startOfDay();
        $dias = max(7, min(366, $dias ?? (int) config('financeiro.projecao.dias', 90)));
        $fim = $inicio->addDays($dias - 1);

        $taxa = null;
        $taxaOrigem = null;

        if ($cenario === 'esperado') {
            ['taxa' => $taxa, 'origem' => $taxaOrigem] = $this->taxaDeRecebimento($inicio);
        }

        $entradasPorDia = [];
        $saidasPorDia = [];

        // Recebimentos: o saldo restante de cada fatura aberta, no dia do vencimento (ou, se já venceu, daqui a alguns dias).
        $faturas = ['quantidade' => 0, 'bruto' => 0.0, 'ponderado' => 0.0];
        $diasAtrasadas = max(0, (int) config('financeiro.projecao.dias_recebimento_atrasadas', 7));
        $fatorAtrasadas = (float) config('financeiro.projecao.fator_atrasadas', 0.5);

        foreach ($this->faturasAbertas($fim) as $fatura) {
            $restante = round(max(0.0, (float) $fatura->valor_restante), 2);

            if ($restante <= 0) {
                continue;
            }

            $vencida = $fatura->vencimento->lt($inicio);
            $dia = $vencida ? $inicio->addDays($diasAtrasadas) : CarbonImmutable::parse($fatura->vencimento);

            if ($dia->gt($fim)) {
                continue;
            }

            $peso = match ($cenario) {
                'otimista' => 1.0,
                'conservador' => 0.0,
                default => $vencida ? (float) $taxa * $fatorAtrasadas : (float) $taxa,
            };

            $faturas['quantidade']++;
            $faturas['bruto'] += $restante;
            $faturas['ponderado'] += $restante * $peso;
            $entradasPorDia[$dia->toDateString()] = ($entradasPorDia[$dia->toDateString()] ?? 0.0) + $restante * $peso;
        }

        // Pagamentos: contas abertas (as vencidas saem já no primeiro dia) e recorrentes que ainda não viraram conta.
        $contas = ['quantidade' => 0, 'valor' => 0.0];

        foreach (ContaPagar::query()->whereIn('status', [StatusContaPagar::Pendente->value, StatusContaPagar::Atrasado->value])->whereDate('vencimento', '<=', $fim->toDateString())->get(['id', 'valor', 'vencimento']) as $conta) {
            $dia = $conta->vencimento->lt($inicio) ? $inicio : CarbonImmutable::parse($conta->vencimento);
            $contas['quantidade']++;
            $contas['valor'] += (float) $conta->valor;
            $saidasPorDia[$dia->toDateString()] = ($saidasPorDia[$dia->toDateString()] ?? 0.0) + (float) $conta->valor;
        }

        $recorrentes = ['quantidade' => 0, 'valor' => 0.0];

        foreach ($this->recorrentes->proximasSemConta($inicio, $fim) as $previsto) {
            $recorrentes['quantidade']++;
            $recorrentes['valor'] += $previsto['valor'];
            $chave = $previsto['vencimento']->toDateString();
            $saidasPorDia[$chave] = ($saidasPorDia[$chave] ?? 0.0) + $previsto['valor'];
        }

        // Linha do tempo: saldo ao fim de cada dia.
        $saldoInicial = $this->saldoBancario($inicio);
        $saldo = $saldoInicial;
        $menor = $saldoInicial;
        $dataMenor = $inicio;
        $primeiraNegativa = $saldoInicial < 0 ? $inicio : null;
        $totalEntradas = 0.0;
        $totalSaidas = 0.0;
        $semanas = [];

        for ($i = 0; $i < $dias; $i++) {
            $dia = $inicio->addDays($i);
            $chave = $dia->toDateString();
            $entradas = $entradasPorDia[$chave] ?? 0.0;
            $saidas = $saidasPorDia[$chave] ?? 0.0;
            $saldo += $entradas - $saidas;
            $totalEntradas += $entradas;
            $totalSaidas += $saidas;

            if ($saldo < $menor) {
                $menor = $saldo;
                $dataMenor = $dia;
            }

            if ($saldo < 0 && $primeiraNegativa === null) {
                $primeiraNegativa = $dia;
            }

            $indice = intdiv($i, 7);
            $semanas[$indice] ??= ['inicio' => $dia, 'fim' => $dia, 'entradas' => 0.0, 'saidas' => 0.0, 'saldo_final' => $saldo, 'negativa' => false];
            $semanas[$indice]['fim'] = $dia;
            $semanas[$indice]['entradas'] += $entradas;
            $semanas[$indice]['saidas'] += $saidas;
            $semanas[$indice]['saldo_final'] = $saldo;
            $semanas[$indice]['negativa'] = $semanas[$indice]['negativa'] || $saldo < 0;
        }

        return [
            'cenario' => $cenario,
            'cenario_nome' => self::CENARIOS[$cenario],
            'inicio' => $inicio,
            'fim' => $fim,
            'dias' => $dias,
            'saldo_inicial' => round($saldoInicial, 2),
            'taxa' => $taxa,
            'taxa_origem' => $taxaOrigem,
            'entradas' => round($totalEntradas, 2),
            'saidas' => round($totalSaidas, 2),
            'saldo_final' => round($saldo, 2),
            'menor_saldo' => round($menor, 2),
            'data_menor_saldo' => $dataMenor,
            'primeira_data_negativa' => $primeiraNegativa,
            'semanas' => array_values(array_map(fn (array $s): array => [
                'inicio' => $s['inicio'],
                'fim' => $s['fim'],
                'entradas' => round($s['entradas'], 2),
                'saidas' => round($s['saidas'], 2),
                'saldo_final' => round($s['saldo_final'], 2),
                'negativa' => $s['negativa'],
            ], $semanas)),
            'origem' => [
                'faturas' => ['quantidade' => $faturas['quantidade'], 'bruto' => round($faturas['bruto'], 2), 'ponderado' => round($faturas['ponderado'], 2)],
                'contas' => ['quantidade' => $contas['quantidade'], 'valor' => round($contas['valor'], 2)],
                'recorrentes' => ['quantidade' => $recorrentes['quantidade'], 'valor' => round($recorrentes['valor'], 2)],
            ],
            'conciliacao' => $this->situacaoDaConciliacao(),
        ];
    }

    /**
     * Saldo das transações bancárias lançadas até a data, somado ao saldo-base da configuração.
     */
    public function saldoBancario(CarbonInterface $ate): float
    {
        $saldo = TransacaoBancaria::query()
            ->whereDate('data_transacao', '<=', CarbonImmutable::parse($ate)->toDateString())
            ->selectRaw("sum(case when tipo = 'entrada' then valor else -valor end) as saldo")
            ->value('saldo');

        return round((float) $saldo + (float) config('financeiro.projecao.saldo_base', 0), 2);
    }

    /**
     * Quanto do que vence costuma ser recebido: nos últimos meses completos, o que foi pago sobre o que foi faturado. Sem histórico
     * suficiente, vale o parâmetro da escola. O cálculo é guardado em cache por algumas horas.
     *
     * @return array{taxa: float, origem: string}
     */
    public function taxaDeRecebimento(CarbonInterface $referencia): array
    {
        $meses = max(1, (int) config('financeiro.projecao.meses_historico', 6));
        $fim = CarbonImmutable::parse($referencia)->startOfMonth()->subDay();
        $inicio = $fim->startOfMonth()->subMonthsNoOverflow($meses - 1);

        return Cache::remember("fluxo_projetado.taxa.{$inicio->format('Y-m')}.{$fim->format('Y-m')}", now()->addHours(6), function () use ($inicio, $fim): array {
            $faturado = 0.0;
            $recebido = 0.0;

            $faturas = Fatura::query()
                ->where('status', '!=', StatusFatura::Cancelado->value)
                ->whereDate('vencimento', '>=', $inicio->toDateString())
                ->whereDate('vencimento', '<=', $fim->toDateString())
                ->with(['itens', 'transacoes'])
                ->get();

            foreach ($faturas as $fatura) {
                $valor = (float) $fatura->valor;
                $faturado += $valor;
                $recebido += min($valor, (float) $fatura->valor_pago);
            }

            $padrao = max(0.0, min(1.0, (float) config('financeiro.projecao.taxa_padrao', 0.85)));

            if ($faturado < 1.0) {
                return ['taxa' => $padrao, 'origem' => 'padrao'];
            }

            return ['taxa' => round(max(0.0, min(1.0, $recebido / $faturado)), 4), 'origem' => 'historico'];
        });
    }

    /**
     * @return Collection<int, Fatura>
     */
    private function faturasAbertas(CarbonInterface $ate): Collection
    {
        return Fatura::query()
            ->whereIn('status', [StatusFatura::Pendente->value, StatusFatura::Parcial->value, StatusFatura::Atrasado->value])
            ->whereDate('vencimento', '<=', CarbonImmutable::parse($ate)->toDateString())
            ->with(['itens', 'transacoes'])
            ->get();
    }

    /**
     * @return array{ultimo_lancamento: ?CarbonImmutable, ultima_conciliada: ?CarbonImmutable, nao_conciliadas: int}
     */
    private function situacaoDaConciliacao(): array
    {
        $ultimo = TransacaoBancaria::query()->max('data_transacao');
        $conciliada = TransacaoBancaria::query()->where('conciliado', true)->max('data_transacao');

        return [
            'ultimo_lancamento' => $ultimo ? CarbonImmutable::parse($ultimo) : null,
            'ultima_conciliada' => $conciliada ? CarbonImmutable::parse($conciliada) : null,
            'nao_conciliadas' => TransacaoBancaria::query()->where('conciliado', false)->count(),
        ];
    }
}
