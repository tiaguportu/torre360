<?php

declare(strict_types=1);

namespace App\Services\Ponto;

use App\Enums\TipoTratamentoPonto;
use App\Models\DiaNaoLetivo;
use App\Models\Funcionario;
use App\Models\PontoAcordoBancoHoras;
use App\Models\PontoConfiguracao;
use App\Models\PontoJornada;
use App\Models\PontoRegistro;
use App\Models\PontoTratamento;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Transforma marcações e ajustes aprovados em jornada apurada: horas trabalhadas, saldo do dia e alertas legais.
 *
 * Regras (documentadas em docs/ponto_banco_horas_roadmap.md):
 * - As marcações do dia formam pares entrada/saída na ordem; quantidade ímpar deixa o dia "incompleto" e ele não gera saldo
 *   até ser tratado.
 * - Os segundos são desprezados (a marcação vale pelo minuto).
 * - Tolerância da CLT, art. 58, § 1º: se o saldo do dia (a mais ou a menos) é de até 10 minutos, é zerado; passou disso,
 *   conta o tempo todo (Súmula 366 do TST).
 * - Feriado nacional tem jornada prevista zero.
 * - Limitação conhecida: jornada que atravessa a meia-noite (turno noturno) não é tratada; cada marcação pertence ao dia
 *   do calendário em que foi feita.
 */
class PontoApuracaoService
{
    private const INTERJORNADA_MINIMA_MINUTOS = 660; // 11 h (CLT, art. 66)

    private const LIMITE_DIARIO_MINUTOS = 600; // 8 h + até 2 h extras (CLT, art. 59)

    /**
     * @return array<string, mixed>
     */
    public function apurarDia(Funcionario $funcionario, CarbonInterface $dia, ?CarbonInterface $hoje = null): array
    {
        $dia = $dia->copy()->startOfDay();
        $carga = $this->carregar($funcionario, $dia->copy()->subDay(), $dia);
        $ontem = $this->marcacoesEfetivas($carga['registros'], $carga['tratamentos'], $dia->copy()->subDay()->toDateString());

        return $this->calcularDia($dia, $carga, $this->ultima($ontem), $hoje ?? today());
    }

    /**
     * Espelho de ponto do mês: um item por dia do calendário e os totais.
     *
     * @return array{funcionario_id: int, ano: int, mes: int, dias: list<array<string, mixed>>, totais: array<string, int>}
     */
    public function espelho(Funcionario $funcionario, int $ano, int $mes, ?CarbonInterface $hoje = null): array
    {
        $hoje ??= today();
        $inicio = Carbon::create($ano, $mes, 1)->startOfDay();
        $fim = $inicio->copy()->endOfMonth()->startOfDay();

        $carga = $this->carregar($funcionario, $inicio->copy()->subDay(), $fim);
        $acordos = PontoAcordoBancoHoras::query()->where('funcionario_id', $funcionario->id)->get();

        $ultimaDeOntem = $this->ultima($this->marcacoesEfetivas($carga['registros'], $carga['tratamentos'], $inicio->copy()->subDay()->toDateString()));
        $dias = [];
        $totais = ['previsto' => 0, 'trabalhado' => 0, 'abono' => 0, 'extras_banco' => 0, 'extras_a_pagar' => 0, 'debitos' => 0, 'faltas' => 0, 'incompletos' => 0, 'dias_com_alerta' => 0];

        for ($dia = $inicio->copy(); $dia->lte($fim); $dia->addDay()) {
            $apuracao = $this->calcularDia($dia->copy(), $carga, $ultimaDeOntem, $hoje);
            $apuracao['banco'] = $acordos->contains(fn (PontoAcordoBancoHoras $a): bool => $a->vigenteEm($dia));
            $dias[] = $apuracao;

            $efetivas = $this->marcacoesEfetivas($carga['registros'], $carga['tratamentos'], $dia->toDateString());
            $ultimaDeOntem = $this->ultima($efetivas) ?? $ultimaDeOntem;

            $this->somar($totais, $apuracao);
        }

        return ['funcionario_id' => $funcionario->id, 'ano' => $ano, 'mes' => $mes, 'dias' => $dias, 'totais' => $totais];
    }

    /**
     * @param  array<string, int>  $totais
     * @param  array<string, mixed>  $apuracao
     */
    private function somar(array &$totais, array $apuracao): void
    {
        $totais['previsto'] += $apuracao['previsto'];
        $totais['trabalhado'] += $apuracao['trabalhado'];
        $totais['abono'] += $apuracao['abono'];

        if ($apuracao['alertas'] !== []) {
            $totais['dias_com_alerta']++;
        }

        if ($apuracao['situacao'] === 'incompleto') {
            $totais['incompletos']++;
        }

        if ($apuracao['situacao'] === 'falta') {
            $totais['faltas']++;
        }

        $saldo = $apuracao['saldo'];

        if ($saldo === null) {
            return;
        }

        if ($saldo < 0) {
            $totais['debitos'] += -$saldo;
        } elseif ($saldo > 0) {
            // Sem acordo de banco de horas, a hora excedente é hora extra a pagar.
            $totais[($apuracao['banco'] ?? false) ? 'extras_banco' : 'extras_a_pagar'] += $saldo;
        }
    }

    /**
     * @return array{registros: Collection<int, PontoRegistro>, tratamentos: Collection<int, PontoTratamento>, jornadas: Collection<int, PontoJornada>, config: ?PontoConfiguracao}
     */
    private function carregar(Funcionario $funcionario, CarbonInterface $de, CarbonInterface $ate): array
    {
        return [
            'registros' => PontoRegistro::query()
                ->where('funcionario_id', $funcionario->id)
                ->whereBetween('momento', [$de->copy()->startOfDay(), $ate->copy()->endOfDay()])
                ->orderBy('momento')
                ->orderBy('id')
                ->get(),
            'tratamentos' => PontoTratamento::query()
                ->aprovados()
                ->where('funcionario_id', $funcionario->id)
                ->whereDate('data', '>=', $de->toDateString())
                ->whereDate('data', '<=', $ate->toDateString())
                ->get(),
            'jornadas' => PontoJornada::query()->where('funcionario_id', $funcionario->id)->get(),
            'config' => $funcionario->unidade_id
                ? PontoConfiguracao::query()->where('unidade_id', $funcionario->unidade_id)->first()
                : null,
        ];
    }

    /**
     * Marcações que valem num dia: as registradas (menos as desconsideradas por ajuste aprovado) e as incluídas por ajuste.
     *
     * @param  Collection<int, PontoRegistro>  $registros
     * @param  Collection<int, PontoTratamento>  $tratamentos
     * @return list<array{momento: Carbon, registro_id: ?int, ajustada: bool}>
     */
    private function marcacoesEfetivas(Collection $registros, Collection $tratamentos, string $dia): array
    {
        $desconsiderados = $tratamentos
            ->filter(fn (PontoTratamento $t): bool => $t->tipo === TipoTratamentoPonto::Desconsiderar && $t->registro_id !== null)
            ->pluck('registro_id')
            ->all();

        $marcacoes = $registros
            ->filter(fn (PontoRegistro $r): bool => $r->momento->toDateString() === $dia && ! in_array($r->id, $desconsiderados, true))
            ->map(fn (PontoRegistro $r): array => ['momento' => Carbon::instance($r->momento)->startOfMinute(), 'registro_id' => $r->id, 'ajustada' => false]);

        $inclusoes = $tratamentos
            ->filter(fn (PontoTratamento $t): bool => $t->tipo === TipoTratamentoPonto::Inclusao && $t->momento !== null && $t->momento->toDateString() === $dia)
            ->map(fn (PontoTratamento $t): array => ['momento' => Carbon::instance($t->momento)->startOfMinute(), 'registro_id' => null, 'ajustada' => true]);

        return $marcacoes->concat($inclusoes)->sortBy(fn (array $m): int => $m['momento']->timestamp)->values()->all();
    }

    /**
     * @param  list<array{momento: Carbon, registro_id: ?int, ajustada: bool}>  $marcacoes
     */
    private function ultima(array $marcacoes): ?Carbon
    {
        return $marcacoes === [] ? null : end($marcacoes)['momento'];
    }

    /**
     * @param  array{registros: Collection<int, PontoRegistro>, tratamentos: Collection<int, PontoTratamento>, jornadas: Collection<int, PontoJornada>, config: ?PontoConfiguracao}  $carga
     * @return array<string, mixed>
     */
    private function calcularDia(CarbonInterface $dia, array $carga, ?Carbon $ultimaDeOntem, CarbonInterface $hoje): array
    {
        $data = $dia->toDateString();
        $marcacoes = $this->marcacoesEfetivas($carga['registros'], $carga['tratamentos'], $data);
        $feriado = DiaNaoLetivo::getFeriadoNacional(Carbon::instance($dia));
        $jornada = $carga['jornadas']->first(fn (PontoJornada $j): bool => $j->vigenteEm($dia));
        $previsto = $feriado !== null ? 0 : ($jornada?->minutosPrevistosEm($dia) ?? 0);
        $abono = (int) $carga['tratamentos']
            ->filter(fn (PontoTratamento $t): bool => $t->tipo === TipoTratamentoPonto::Abono && $t->data->toDateString() === $data)
            ->sum('minutos_abono');

        [$trabalhado, $intervalos] = $this->trabalhadoEIntervalos($marcacoes);
        $incompleto = count($marcacoes) % 2 === 1;
        $alertas = [];

        if ($marcacoes !== [] && $jornada === null) {
            $alertas[] = ['codigo' => 'sem_jornada', 'mensagem' => 'O funcionário não tem jornada cadastrada para esta data.'];
        }

        if ($feriado !== null && $marcacoes !== []) {
            $alertas[] = ['codigo' => 'trabalho_em_feriado', 'mensagem' => "Trabalho em feriado ({$feriado}): confira o pagamento diferenciado."];
        }

        if ($marcacoes !== [] && $ultimaDeOntem !== null && ($marcacoes[0]['momento']->timestamp - $ultimaDeOntem->timestamp) / 60 < self::INTERJORNADA_MINIMA_MINUTOS) {
            $alertas[] = ['codigo' => 'interjornada', 'mensagem' => 'Menos de 11 horas entre o fim da jornada anterior e o início desta (CLT, art. 66).'];
        }

        if (! $incompleto && $trabalhado > 360 && max([0, ...$intervalos]) < 60) {
            $alertas[] = ['codigo' => 'intrajornada', 'mensagem' => 'Jornada acima de 6 horas sem intervalo de pelo menos 1 hora (CLT, art. 71).'];
        } elseif (! $incompleto && $trabalhado > 240 && $trabalhado <= 360 && max([0, ...$intervalos]) < 15) {
            $alertas[] = ['codigo' => 'intrajornada', 'mensagem' => 'Jornada entre 4 e 6 horas sem intervalo de pelo menos 15 minutos (CLT, art. 71, § 1º).'];
        }

        if ($trabalhado > self::LIMITE_DIARIO_MINUTOS) {
            $alertas[] = ['codigo' => 'excesso_jornada', 'mensagem' => 'Mais de 10 horas trabalhadas no dia: o limite é 8 horas mais até 2 horas extras (CLT, art. 59).'];
        }

        $situacao = match (true) {
            $dia->toDateString() > $hoje->toDateString() => 'futuro',
            $dia->toDateString() === $hoje->toDateString() => 'em_andamento',
            $incompleto => 'incompleto',
            $marcacoes === [] && $previsto === 0 && $abono === 0 => 'folga',
            $marcacoes === [] && $abono >= $previsto => 'abonado',
            $marcacoes === [] => 'falta',
            default => 'ok',
        };

        $saldo = match ($situacao) {
            'falta' => -($previsto - $abono),
            'ok' => $this->aplicarTolerancia($trabalhado + $abono - $previsto, $carga['config']),
            'abonado', 'folga' => 0,
            default => null,
        };

        return [
            'data' => $data,
            'dia_semana' => $dia->dayOfWeekIso,
            'feriado' => $feriado,
            'marcacoes' => array_map(fn (array $m): array => [
                'hora' => $m['momento']->format('H:i'),
                'registro_id' => $m['registro_id'],
                'ajustada' => $m['ajustada'],
            ], $marcacoes),
            'previsto' => $previsto,
            'trabalhado' => $trabalhado,
            'abono' => $abono,
            'saldo' => $saldo,
            'situacao' => $situacao,
            'alertas' => $alertas,
        ];
    }

    /**
     * Soma os pares entrada/saída e devolve também os intervalos entre pares (saída → próxima entrada), em minutos.
     *
     * @param  list<array{momento: Carbon, registro_id: ?int, ajustada: bool}>  $marcacoes
     * @return array{0: int, 1: list<int>}
     */
    private function trabalhadoEIntervalos(array $marcacoes): array
    {
        $trabalhado = 0;
        $intervalos = [];

        for ($i = 0; $i + 1 < count($marcacoes); $i += 2) {
            $trabalhado += intdiv($marcacoes[$i + 1]['momento']->timestamp - $marcacoes[$i]['momento']->timestamp, 60);

            if (isset($marcacoes[$i + 2])) {
                $intervalos[] = intdiv($marcacoes[$i + 2]['momento']->timestamp - $marcacoes[$i + 1]['momento']->timestamp, 60);
            }
        }

        return [$trabalhado, $intervalos];
    }

    private function aplicarTolerancia(int $saldoBruto, ?PontoConfiguracao $config): int
    {
        // A CLT fixa o teto em 10 minutos; a unidade pode adotar um limite menor, nunca maior.
        $tolerancia = min((int) ($config?->tolerancia_diaria_minutos ?? config('ponto.tolerancia_diaria_minutos', 10)), 10);

        return abs($saldoBruto) <= $tolerancia ? 0 : $saldoBruto;
    }
}
