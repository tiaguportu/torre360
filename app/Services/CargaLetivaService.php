<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SituacaoCargaLetiva;
use App\Models\CronogramaAula;
use App\Models\DiaNaoLetivo;
use App\Models\EtapaEnsino;
use App\Models\EtapaEnsinoAgregada;
use App\Models\PeriodoLetivo;
use App\Models\Serie;
use App\Models\Turma;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Carga letiva do ano (Onda 49): quantos dias e horas de aula cada turma tem previstos, quantos já deram e quantos vai
 * dar até o fim do período, contra o mínimo da LDB (art. 24, I; 31, II).
 *
 * As horas são de relógio (60 minutos), somadas da duração real de cada aula do cronograma: a hora-aula de 45 ou 50
 * minutos não infla o total (Parecer CNE/CEB nº 8/2004). Uma aula conta como realizada quando tem chamada lançada ou
 * conteúdo ministrado registrado. A aula marcada como "não realizada" sai da conta; a reposição agendada entra.
 *
 * É um apoio ao planejamento: a validação do calendário é do órgão do sistema de ensino.
 */
class CargaLetivaService
{
    /**
     * @return array{
     *     turma: Turma, periodo: ?PeriodoLetivo, etapa: ?string, minimo: ?array{horas: int, dias: int},
     *     aulas_previstas: int, aulas_realizadas: int, aulas_nao_realizadas: int, aulas_sem_registro: int, reposicoes: int,
     *     dias_previstos: int, dias_realizados: int, dias_projetados: int,
     *     horas_previstas: float, horas_realizadas: float, horas_projetadas: float,
     *     dias_faltantes: int, horas_faltantes: float, situacao: SituacaoCargaLetiva
     * }
     */
    public function apurar(Turma $turma, ?CarbonInterface $hoje = null): array
    {
        $hoje = Carbon::parse(($hoje ?? today())->toDateString());
        $periodo = $turma->periodo_letivo_id ? PeriodoLetivo::query()->find($turma->periodo_letivo_id) : null;
        $etapa = $this->etapaDaTurma($turma);
        $minimo = $etapa ? $this->minimoDaEtapa($etapa) : null;
        $naoLetivos = $this->diasNaoLetivos($turma, $periodo);

        $total = ['aulas' => 0, 'min' => 0, 'dias' => []];
        $feito = ['aulas' => 0, 'min' => 0, 'dias' => []];
        $futuro = ['min' => 0, 'dias' => []];
        $naoRealizadas = 0;
        $semRegistro = 0;
        $reposicoes = 0;

        foreach ($this->aulasDoPeriodo($turma, $periodo) as $aula) {
            $dia = $aula->data->toDateString();

            if (isset($naoLetivos[$dia])) {
                continue;
            }

            if ($aula->naoFoiRealizada()) {
                $naoRealizadas++;

                continue;
            }

            $minutos = $this->minutos($aula->hora_inicio, $aula->hora_fim);

            $total['aulas']++;
            $total['min'] += $minutos;
            $total['dias'][$dia] = true;

            if ($aula->ehReposicao()) {
                $reposicoes++;
            }

            if ($aula->data->lte($hoje)) {
                if ($aula->com_chamada || $this->temConteudo($aula)) {
                    $feito['aulas']++;
                    $feito['min'] += $minutos;
                    $feito['dias'][$dia] = true;
                } else {
                    $semRegistro++;
                }
            } else {
                $futuro['min'] += $minutos;
                $futuro['dias'][$dia] = true;
            }
        }

        $diasPrevistos = count($total['dias']);
        $diasProjetados = count($feito['dias'] + $futuro['dias']);

        if ($periodo && config('frequencia_legal.modo_dias') === 'calendario') {
            $calendario = $this->diasDoCalendario($periodo, $naoLetivos);
            $diasPrevistos = count($calendario);
            $diasProjetados = count($feito['dias'] + array_filter($calendario, fn (bool $_, string $dia): bool => $dia > $hoje->toDateString(), ARRAY_FILTER_USE_BOTH));
        }

        $minutosProjetados = $feito['min'] + $futuro['min'];

        return [
            'turma' => $turma,
            'periodo' => $periodo,
            'etapa' => $etapa,
            'minimo' => $minimo,
            'aulas_previstas' => $total['aulas'],
            'aulas_realizadas' => $feito['aulas'],
            'aulas_nao_realizadas' => $naoRealizadas,
            'aulas_sem_registro' => $semRegistro,
            'reposicoes' => $reposicoes,
            'dias_previstos' => $diasPrevistos,
            'dias_realizados' => count($feito['dias']),
            'dias_projetados' => $diasProjetados,
            'horas_previstas' => $this->horas($total['min']),
            'horas_realizadas' => $this->horas($feito['min']),
            'horas_projetadas' => $this->horas($minutosProjetados),
            'dias_faltantes' => $minimo ? max(0, $minimo['dias'] - $diasProjetados) : 0,
            'horas_faltantes' => $minimo ? $this->horas(max(0, $minimo['horas'] * 60 - $minutosProjetados)) : 0.0,
            'situacao' => $this->situacao($minimo, $total['min'], $diasPrevistos, $minutosProjetados, $diasProjetados),
        ];
    }

    /**
     * Todas as turmas do período (menos as canceladas), na ordem do nome.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function apurarPeriodo(PeriodoLetivo $periodo, ?CarbonInterface $hoje = null): Collection
    {
        return Turma::query()
            ->where('periodo_letivo_id', $periodo->id)
            ->where('status', '!=', 'cancelada')
            ->orderBy('nome')
            ->get()
            ->map(fn (Turma $turma): array => $this->apurar($turma, $hoje));
    }

    /**
     * Dias e horas para o histórico escolar: o que o cronograma da turma mostra (realizado mais o que falta dar), ou null
     * quando a turma não tem cronograma e o histórico segue com o padrão antigo.
     *
     * @return array{dias: int, horas: int}|null
     */
    public function paraHistorico(Turma $turma, ?CarbonInterface $hoje = null): ?array
    {
        $apuracao = $this->apurar($turma, $hoje);

        if ($apuracao['aulas_previstas'] === 0) {
            return null;
        }

        return ['dias' => $apuracao['dias_projetados'], 'horas' => (int) round($apuracao['horas_projetadas'])];
    }

    /**
     * Aulas já passadas sem chamada nem conteúdo registrados: ou aconteceram e falta lançar, ou não aconteceram e pedem
     * reposição. Quem decide é a coordenação.
     *
     * @return Collection<int, CronogramaAula>
     */
    public function aulasSemRegistro(Turma $turma, ?CarbonInterface $hoje = null): Collection
    {
        $hoje = Carbon::parse(($hoje ?? today())->toDateString());
        $periodo = $turma->periodo_letivo_id ? PeriodoLetivo::query()->find($turma->periodo_letivo_id) : null;
        $naoLetivos = $this->diasNaoLetivos($turma, $periodo);

        return $this->aulasDoPeriodo($turma, $periodo)
            ->filter(fn (CronogramaAula $aula): bool => $aula->data->lte($hoje)
                && ! $aula->naoFoiRealizada()
                && ! $aula->com_chamada
                && ! $this->temConteudo($aula)
                && ! isset($naoLetivos[$aula->data->toDateString()]))
            ->sortBy('data')
            ->values();
    }

    /**
     * Aulas marcadas como não realizadas, com as reposições já agendadas.
     *
     * @return Collection<int, CronogramaAula>
     */
    public function aulasNaoRealizadas(Turma $turma): Collection
    {
        $periodo = $turma->periodo_letivo_id ? PeriodoLetivo::query()->find($turma->periodo_letivo_id) : null;

        return CronogramaAula::query()
            ->where('turma_id', $turma->id)
            ->whereNotNull('nao_realizada_em')
            ->when($periodo, fn ($consulta) => $consulta->whereDate('data', '>=', $periodo->data_inicio)->whereDate('data', '<=', $periodo->data_fim))
            ->with(['reposicoes', 'disciplina'])
            ->orderBy('data')
            ->get();
    }

    /**
     * A etapa da turma (infantil, fundamental, medio) ou null quando a etapa existe mas não tem mínimo definido.
     */
    public function etapaDaTurma(Turma $turma): ?string
    {
        $codigoAgregada = null;

        if ($turma->etapa_ensino_agregada_id) {
            $codigoAgregada = EtapaEnsinoAgregada::query()->whereKey($turma->etapa_ensino_agregada_id)->value('codigo');
        } elseif ($turma->etapa_ensino_id) {
            $agregadaId = EtapaEnsino::query()->whereKey($turma->etapa_ensino_id)->value('etapa_ensino_agregada_id');
            $codigoAgregada = $agregadaId ? EtapaEnsinoAgregada::query()->whereKey($agregadaId)->value('codigo') : null;
        }

        if ($codigoAgregada === null) {
            return config('frequencia_legal.etapa_padrao');
        }

        return config('frequencia_legal.etapas.'.$codigoAgregada);
    }

    /**
     * @return array{horas: int, dias: int}|null
     */
    public function minimoDaEtapa(string $etapa): ?array
    {
        $minimo = config("frequencia_legal.minimos.{$etapa}");

        return is_array($minimo) ? ['horas' => (int) $minimo['horas'], 'dias' => (int) $minimo['dias']] : null;
    }

    /**
     * @return Collection<int, CronogramaAula>
     */
    private function aulasDoPeriodo(Turma $turma, ?PeriodoLetivo $periodo): Collection
    {
        return CronogramaAula::query()
            ->where('turma_id', $turma->id)
            ->when($periodo, fn ($consulta) => $consulta->whereDate('data', '>=', $periodo->data_inicio)->whereDate('data', '<=', $periodo->data_fim))
            ->withExists(['frequencias as com_chamada' => fn ($consulta) => $consulta->whereNotNull('situacao')])
            ->get();
    }

    /**
     * Datas (Y-m-d => true) em que a turma não tem aula: dias não letivos ativos do período, gerais ou do curso dela.
     *
     * @return array<string, bool>
     */
    private function diasNaoLetivos(Turma $turma, ?PeriodoLetivo $periodo): array
    {
        if (! $periodo) {
            return [];
        }

        $consulta = DiaNaoLetivo::query()
            ->where('periodo_letivo_id', $periodo->id)
            ->where('flag_ativo', true);

        // Bancos montados pela cadeia antiga de migrations perderam a coluna `curso_id` desta tabela (a migration que refina as
        // relações a remove fora do SQLite): sem ela, o dia não letivo vale para todos os cursos.
        if (Schema::hasColumn('dia_nao_letivo', 'curso_id')) {
            $cursoId = $turma->serie_id ? Serie::query()->whereKey($turma->serie_id)->value('curso_id') : null;

            $consulta->where(fn ($q) => $q->whereNull('curso_id')->when($cursoId, fn ($qq) => $qq->orWhere('curso_id', $cursoId)));
        }

        return $consulta
            ->get()
            ->mapWithKeys(fn (DiaNaoLetivo $dia): array => [Carbon::parse($dia->data)->toDateString() => true])
            ->all();
    }

    /**
     * Dias do período nos dias da semana letivos, sem dia não letivo nem feriado nacional.
     *
     * @param  array<string, bool>  $naoLetivos
     * @return array<string, bool>
     */
    private function diasDoCalendario(PeriodoLetivo $periodo, array $naoLetivos): array
    {
        $semana = array_map('intval', (array) config('frequencia_legal.dias_da_semana', [1, 2, 3, 4, 5]));
        $dias = [];

        for ($dia = Carbon::parse($periodo->data_inicio); $dia->lte($periodo->data_fim); $dia = $dia->copy()->addDay()) {
            $chave = $dia->toDateString();

            if (in_array($dia->dayOfWeekIso, $semana, true) && ! isset($naoLetivos[$chave]) && DiaNaoLetivo::getFeriadoNacional($dia) === null) {
                $dias[$chave] = true;
            }
        }

        return $dias;
    }

    private function temConteudo(CronogramaAula $aula): bool
    {
        return trim((string) $aula->conteudo_ministrado) !== '';
    }

    private function minutos(?string $inicio, ?string $fim): int
    {
        if (! $inicio || ! $fim) {
            return 0;
        }

        $paraMinutos = function (string $hora): int {
            [$h, $m] = array_pad(explode(':', $hora), 2, '0');

            return ((int) $h) * 60 + (int) $m;
        };

        return max(0, $paraMinutos($fim) - $paraMinutos($inicio));
    }

    private function horas(int|float $minutos): float
    {
        return round($minutos / 60, 1);
    }

    /**
     * @param  array{horas: int, dias: int}|null  $minimo
     */
    private function situacao(?array $minimo, int $minutosPrevistos, int $diasPrevistos, int $minutosProjetados, int $diasProjetados): SituacaoCargaLetiva
    {
        if (! $minimo) {
            return SituacaoCargaLetiva::SemMinimo;
        }

        $minimoMinutos = $minimo['horas'] * 60;

        // O calendário nem fecha o mínimo, mesmo que toda aula prevista aconteça.
        if ($minutosPrevistos < $minimoMinutos || $diasPrevistos < $minimo['dias']) {
            return SituacaoCargaLetiva::Insuficiente;
        }

        // Fecha no papel, mas o que já passou sem registro (ou sem reposição) puxa a projeção para baixo do mínimo.
        if ($minutosProjetados < $minimoMinutos || $diasProjetados < $minimo['dias']) {
            return SituacaoCargaLetiva::EmRisco;
        }

        return SituacaoCargaLetiva::EmDia;
    }
}
