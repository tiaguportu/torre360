<?php

namespace App\Services;

use App\Models\DiaNaoLetivo;
use App\Models\Preceptoria;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agenda de preceptorias: gera séries recorrentes numa janela de tempo (pulando fins de semana,
 * feriados nacionais e dias não letivos quando pedido), detecta choque de horário do professor
 * e reagenda uma preceptoria existente (o que o calendário de arrastar e soltar chama).
 */
class PreceptoriaAgendaService
{
    /** Duração assumida quando a preceptoria não tem hora de término. */
    public const DURACAO_PADRAO_MINUTOS = 30;

    /** Teto de preceptorias criadas de uma vez, para uma janela mal digitada não encher a agenda. */
    public const LIMITE_SERIE = 400;

    public const TIPO_FIM_DE_SEMANA = 'fim_de_semana';

    public const TIPO_FERIADO = 'feriado';

    public const TIPO_NAO_LETIVO = 'nao_letivo';

    /**
     * Datas de uma recorrência. Datas fora do padrão pedido (dia da semana, intervalo de semanas)
     * simplesmente não fazem parte da série; as que fazem parte mas caem em fim de semana, feriado
     * ou dia não letivo (e o respectivo "ignorar" está ligado) vão para `ignoradas`.
     *
     * @param  array{inicio: mixed, fim: mixed, dias_semana?: array<int, int|string>|null, intervalo_semanas?: int|string|null, ignorar_fins_de_semana?: bool|null, ignorar_feriados?: bool|null, ignorar_dias_nao_letivos?: bool|null, periodo_letivo_id?: int|string|null}  $regras
     * @return array{datas: array<int, string>, ignoradas: array<int, array{data: string, tipo: string, motivo: string}>}
     */
    public function calcularDatas(array $regras): array
    {
        $inicio = Carbon::parse($regras['inicio'])->startOfDay();
        $fim = Carbon::parse($regras['fim'])->startOfDay();

        if ($fim->lt($inicio)) {
            return ['datas' => [], 'ignoradas' => []];
        }

        $diasSemana = array_map('intval', $regras['dias_semana'] ?? []);
        $intervalo = max(1, (int) ($regras['intervalo_semanas'] ?? 1));
        $ignorarFimDeSemana = (bool) ($regras['ignorar_fins_de_semana'] ?? false);
        $ignorarFeriados = (bool) ($regras['ignorar_feriados'] ?? false);
        $ignorarNaoLetivos = (bool) ($regras['ignorar_dias_nao_letivos'] ?? false);

        $feriados = $ignorarFeriados ? $this->feriadosNacionais($inicio, $fim) : [];
        $naoLetivos = $ignorarNaoLetivos
            ? $this->diasNaoLetivos($inicio, $fim, $regras['periodo_letivo_id'] ?? null)
            : [];

        $semanaInicial = $inicio->copy()->startOfWeek();
        $datas = [];
        $ignoradas = [];

        for ($dia = $inicio->copy(); $dia->lte($fim); $dia->addDay()) {
            if ($diasSemana !== [] && ! in_array($dia->dayOfWeek, $diasSemana, true)) {
                continue;
            }

            $semanas = (int) round($semanaInicial->diffInDays($dia->copy()->startOfWeek()) / 7);
            if ($semanas % $intervalo !== 0) {
                continue;
            }

            $data = $dia->toDateString();

            if ($ignorarFimDeSemana && $dia->isWeekend()) {
                $ignoradas[] = ['data' => $data, 'tipo' => self::TIPO_FIM_DE_SEMANA, 'motivo' => $dia->isSaturday() ? 'Sábado' : 'Domingo'];

                continue;
            }

            if (isset($feriados[$data])) {
                $ignoradas[] = ['data' => $data, 'tipo' => self::TIPO_FERIADO, 'motivo' => "Feriado: {$feriados[$data]}"];

                continue;
            }

            if (isset($naoLetivos[$data])) {
                $ignoradas[] = ['data' => $data, 'tipo' => self::TIPO_NAO_LETIVO, 'motivo' => $naoLetivos[$data]];

                continue;
            }

            $datas[] = $data;
        }

        return ['datas' => $datas, 'ignoradas' => $ignoradas];
    }

    /**
     * Calcula a série e separa o que seria criado do que bate com outra preceptoria do professor.
     * Serve tanto para a prévia do formulário quanto para a criação de verdade.
     *
     * @param  array<string, mixed>  $regras  as mesmas de calcularDatas()
     * @return array{datas: array<int, string>, ignoradas: array<int, array{data: string, tipo: string, motivo: string}>, conflitos: array<string, Preceptoria>}
     */
    public function simular(array $regras, ?int $professorId, mixed $horaInicio, mixed $horaFim = null): array
    {
        $calculo = $this->calcularDatas($regras);

        $conflitos = $professorId && $horaInicio
            ? $this->conflitosDoProfessor($professorId, $calculo['datas'], $horaInicio, $horaFim)
            : [];

        return [
            'datas' => array_values(array_filter($calculo['datas'], fn (string $data) => ! isset($conflitos[$data]))),
            'ignoradas' => $calculo['ignoradas'],
            'conflitos' => $conflitos,
        ];
    }

    /**
     * Cria a série. Datas em que o professor já tem uma preceptoria sobreposta são puladas (rodar
     * a mesma recorrência duas vezes não duplica nada) e voltam em `conflitos`.
     *
     * @param  array<string, mixed>  $base  ciclo_preceptoria_id, professor_id, hora_inicio, hora_fim, matricula_id
     * @param  array<string, mixed>  $regras  as mesmas de calcularDatas()
     * @return array{criadas: array<int, Preceptoria>, ignoradas: array<int, array{data: string, tipo: string, motivo: string}>, conflitos: array<string, Preceptoria>}
     *
     * @throws DomainException quando a janela geraria mais que LIMITE_SERIE preceptorias
     */
    public function gerarSerie(array $base, array $regras): array
    {
        $simulacao = $this->simular(
            $regras,
            isset($base['professor_id']) ? (int) $base['professor_id'] : null,
            $base['hora_inicio'] ?? null,
            $base['hora_fim'] ?? null,
        );

        if (count($simulacao['datas']) > self::LIMITE_SERIE) {
            throw new DomainException(sprintf(
                'A recorrência geraria %d preceptorias; o limite por vez é %d. Reduza a janela de datas.',
                count($simulacao['datas']),
                self::LIMITE_SERIE,
            ));
        }

        $campos = Arr::only($base, ['ciclo_preceptoria_id', 'professor_id', 'matricula_id', 'hora_inicio', 'hora_fim']);

        $criadas = DB::transaction(fn () => array_map(
            fn (string $data) => Preceptoria::create([...$campos, 'data' => $data]),
            $simulacao['datas'],
        ));

        return ['criadas' => $criadas, 'ignoradas' => $simulacao['ignoradas'], 'conflitos' => $simulacao['conflitos']];
    }

    /**
     * Para cada data, a preceptoria do professor que se sobrepõe ao horário pedido. Horários que só
     * se encostam (14:00–14:30 e 14:30–15:00) não são conflito. A comparação é feita em PHP porque
     * a coluna de horário guarda formatos diferentes conforme quem gravou.
     *
     * @param  array<int, string>  $datas  Y-m-d
     * @return array<string, Preceptoria> indexado pela data
     */
    public function conflitosDoProfessor(int $professorId, array $datas, mixed $horaInicio, mixed $horaFim = null, ?int $ignorarId = null): array
    {
        if ($datas === [] || ! $horaInicio) {
            return [];
        }

        [$inicio, $fim] = $this->intervaloEmMinutos($horaInicio, $horaFim);

        $porData = Preceptoria::query()
            ->where('professor_id', $professorId)
            ->whereDate('data', '>=', min($datas))
            ->whereDate('data', '<=', max($datas))
            ->when($ignorarId, fn ($query) => $query->whereKeyNot($ignorarId))
            ->get()
            ->groupBy(fn (Preceptoria $p) => $p->data->toDateString());

        $conflitos = [];
        foreach ($datas as $data) {
            $conflito = ($porData[$data] ?? collect())->first(function (Preceptoria $outra) use ($inicio, $fim) {
                [$outraInicio, $outraFim] = $this->intervaloEmMinutos($outra->hora_inicio, $outra->hora_fim);

                return $inicio < $outraFim && $outraInicio < $fim;
            });

            if ($conflito) {
                $conflitos[$data] = $conflito;
            }
        }

        return $conflitos;
    }

    /**
     * Feriados nacionais entre as datas, indexados por Y-m-d.
     *
     * @return array<string, string>
     */
    public function feriadosNacionais(CarbonInterface $inicio, CarbonInterface $fim): array
    {
        $feriados = [];

        for ($dia = Carbon::instance($inicio)->startOfDay(); $dia->lte($fim); $dia->addDay()) {
            if ($nome = DiaNaoLetivo::getFeriadoNacional($dia)) {
                $feriados[$dia->toDateString()] = $nome;
            }
        }

        return $feriados;
    }

    /**
     * Dias não letivos cadastrados entre as datas, indexados por Y-m-d. Sem período letivo, vale o
     * de qualquer período. Dias restritos a um curso não entram (preceptoria não pertence a curso).
     *
     * @return array<string, string> motivo pronto para exibir
     */
    public function diasNaoLetivos(CarbonInterface $inicio, CarbonInterface $fim, int|string|null $periodoLetivoId = null): array
    {
        return DiaNaoLetivo::query()
            ->where('flag_ativo', true)
            ->whereDate('data', '>=', $inicio->toDateString())
            ->whereDate('data', '<=', $fim->toDateString())
            ->when($periodoLetivoId, fn ($query) => $query->where('periodo_letivo_id', $periodoLetivoId))
            // curso_id some do banco montado pela cadeia de migrations fora do SQLite (ver Calendario do portal)
            ->when($this->temColunaCurso(), fn ($query) => $query->whereNull('curso_id'))
            ->get(['data', 'descricao'])
            ->mapWithKeys(fn (DiaNaoLetivo $dia) => [
                Carbon::parse($dia->data)->toDateString() => 'Dia não letivo'.($dia->descricao ? ": {$dia->descricao}" : ''),
            ])
            ->all();
    }

    /**
     * Por que uma data não é boa para preceptoria, ou null se for um dia comum.
     * O calendário usa para avisar (sem impedir) quando alguém solta uma preceptoria nesse dia.
     */
    public function motivoDiaEspecial(CarbonInterface $data, int|string|null $periodoLetivoId = null): ?string
    {
        $data = Carbon::instance($data)->startOfDay();

        if ($data->isWeekend()) {
            return $data->isSaturday() ? 'sábado' : 'domingo';
        }

        if ($feriado = DiaNaoLetivo::getFeriadoNacional($data)) {
            return "feriado ({$feriado})";
        }

        $naoLetivo = $this->diasNaoLetivos($data, $data, $periodoLetivoId);

        return $naoLetivo === [] ? null : mb_strtolower(array_values($naoLetivo)[0]);
    }

    /**
     * Move uma preceptoria para outro dia/horário (ou muda a duração). Recusa choque de horário do
     * professor. Quando já há aluno agendado e o novo horário é futuro, os envolvidos são avisados.
     *
     * @param  string  $inicio  Y-m-d\TH:i:s
     * @param  string|null  $fim  Y-m-d\TH:i:s; sem ele mantém a duração atual
     * @return array{ok: bool, mensagem: string, aviso: string|null, notificados: int}
     */
    public function reagendar(Preceptoria $preceptoria, string $inicio, ?string $fim = null, bool $notificar = true): array
    {
        $novoInicio = $this->lerDataHora($inicio);
        if (! $novoInicio) {
            return $this->resultado(false, 'Data ou horário inválido.');
        }

        $horaInicioAntes = $preceptoria->hora_inicio?->format('H:i');
        $horaFimAntes = $preceptoria->hora_fim?->format('H:i');
        $dataAntes = $preceptoria->data->toDateString();

        if ($fim !== null) {
            $novoFim = $this->lerDataHora($fim);

            if (! $novoFim) {
                return $this->resultado(false, 'Data ou horário inválido.');
            }
            if (! $novoFim->isSameDay($novoInicio)) {
                return $this->resultado(false, 'A preceptoria deve começar e terminar no mesmo dia.');
            }
            if ($novoFim->lte($novoInicio)) {
                return $this->resultado(false, 'O horário de término deve ser depois do início.');
            }
        } elseif ($horaInicioAntes && $horaFimAntes) {
            $duracao = $this->minutos($preceptoria->hora_fim) - $this->minutos($preceptoria->hora_inicio);
            $novoFim = $duracao > 0 ? $novoInicio->copy()->addMinutes($duracao) : null;
        } else {
            $novoFim = null;
        }

        if ($novoFim && ! $novoFim->isSameDay($novoInicio)) {
            return $this->resultado(false, 'A preceptoria deve começar e terminar no mesmo dia.');
        }

        $data = $novoInicio->toDateString();
        $horaInicio = $novoInicio->format('H:i');
        $horaFim = $novoFim?->format('H:i');

        if ($data === $dataAntes && $horaInicio === $horaInicioAntes && $horaFim === $horaFimAntes) {
            return $this->resultado(true, 'Nada a alterar.');
        }

        $conflito = $this->conflitosDoProfessor(
            $preceptoria->professor_id,
            [$data],
            $horaInicio,
            $horaFim,
            $preceptoria->getKey(),
        )[$data] ?? null;

        if ($conflito) {
            return $this->resultado(false, sprintf(
                'Conflito: o professor já tem uma preceptoria em %s das %s às %s.',
                $novoInicio->format('d/m/Y'),
                $conflito->hora_inicio->format('H:i'),
                $conflito->hora_fim?->format('H:i') ?? 'fim não informado',
            ));
        }

        $antes = sprintf('%s às %s', Carbon::parse($dataAntes)->format('d/m/Y'), $horaInicioAntes ?? '—');

        $preceptoria->update([
            'data' => $data,
            'hora_inicio' => $horaInicio,
            'hora_fim' => $horaFim,
        ]);

        activity()
            ->performedOn($preceptoria)
            ->event('reagendamento_preceptoria')
            ->withProperties(['antes' => $antes, 'depois' => sprintf('%s às %s', $novoInicio->format('d/m/Y'), $horaInicio)])
            ->log("Preceptoria reagendada de {$antes} para {$novoInicio->format('d/m/Y')} às {$horaInicio}");

        $notificados = 0;
        if ($notificar && $preceptoria->matricula_id && $novoInicio->isFuture()) {
            $preceptoria->refresh()->load(['professor.users', 'matricula.pessoa.responsaveis.users', 'matricula.pessoa.users']);
            $notificados = $preceptoria->relembrarAgendamento($antes)['enviados'];
        }

        $motivo = $this->motivoDiaEspecial($novoInicio, $preceptoria->cicloPreceptoria?->periodo_letivo_id);

        return [
            'ok' => true,
            'mensagem' => sprintf('Preceptoria movida para %s às %s.', $novoInicio->format('d/m/Y'), $horaInicio),
            'aviso' => $motivo ? "Atenção: {$novoInicio->format('d/m/Y')} é {$motivo}." : null,
            'notificados' => $notificados,
        ];
    }

    /**
     * @return array{ok: bool, mensagem: string, aviso: string|null, notificados: int}
     */
    private function resultado(bool $ok, string $mensagem): array
    {
        return ['ok' => $ok, 'mensagem' => $mensagem, 'aviso' => null, 'notificados' => 0];
    }

    private function lerDataHora(string $valor): ?Carbon
    {
        try {
            $lido = Carbon::createFromFormat('Y-m-d\TH:i:s', $valor);
        } catch (\Throwable) {
            return null;
        }

        return $lido ?: null;
    }

    private function minutos(mixed $hora): int
    {
        $hora = $hora instanceof CarbonInterface ? $hora : Carbon::parse($hora);

        return $hora->hour * 60 + $hora->minute;
    }

    /**
     * @return array{0: int, 1: int} início e fim em minutos desde 00:00
     */
    private function intervaloEmMinutos(mixed $horaInicio, mixed $horaFim): array
    {
        $inicio = $this->minutos($horaInicio);
        $fim = $horaFim ? $this->minutos($horaFim) : $inicio + self::DURACAO_PADRAO_MINUTOS;

        return [$inicio, $fim > $inicio ? $fim : $inicio + self::DURACAO_PADRAO_MINUTOS];
    }

    private function temColunaCurso(): bool
    {
        static $tem = null;

        return $tem ??= Schema::hasColumn('dia_nao_letivo', 'curso_id');
    }
}
