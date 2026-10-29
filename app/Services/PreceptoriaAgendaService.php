<?php

namespace App\Services;

use App\Models\DiaNaoLetivo;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Preceptoria;
use App\Models\User;
use App\Notifications\Preceptorias\PreceptoriaAlteradaNotification;
use App\Notifications\Preceptorias\PreceptoriaNotification;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Agenda de preceptorias: gera séries recorrentes numa janela de tempo (pulando fins de semana,
 * feriados nacionais e dias não letivos quando pedido), detecta choque de horário do professor,
 * reagenda uma preceptoria existente (o que o calendário de arrastar e soltar chama) e troca o
 * professor ou o aluno dela, avisando os envolvidos e deixando tudo no histórico.
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
        if ($notificar && $novoInicio->isFuture()) {
            // com aluno, avisa professor, aluno e responsáveis; sem aluno, só o professor (se não foi ele quem mexeu)
            $preceptoria->refresh()->load(['professor.users', 'matricula.pessoa.responsaveis.users', 'matricula.pessoa.users']);

            $notificados = $this->enviarAviso(
                collect([$preceptoria->professor])->filter()->concat($this->circuloDoAluno($preceptoria->matricula?->pessoa)),
                new PreceptoriaAlteradaNotification($preceptoria, PreceptoriaAlteradaNotification::HORARIO, $antes, auth()->user()?->name),
                $preceptoria,
                'reagendamento',
            );
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
     * Matrículas que o usuário pode agendar numa preceptoria: todas para a gestão; para os demais,
     * as suas e as dos alunos vinculados a ele.
     *
     * @return Builder<Matricula>
     */
    public function matriculasAcessiveis(User $user, ?string $papelAtivo): Builder
    {
        if (in_array($papelAtivo, ['super_admin', 'admin', 'secretaria'])) {
            return Matricula::query();
        }

        $pessoasIds = $user->pessoas()->pluck('pessoa.id')->toArray();
        if (empty($pessoasIds)) {
            return Matricula::query()->whereRaw('1 = 0');
        }

        // agrupado em um where só: quem chama pode acrescentar filtros (busca por nome) sem desfazer a restrição
        return Matricula::query()->where(function (Builder $query) use ($user, $papelAtivo, $pessoasIds) {
            $query->whereIn('pessoa_id', $pessoasIds);

            // Se o usuário for responsável, buscar também as matrículas dos alunos vinculados a ele
            if ($papelAtivo === 'responsavel') {
                $query->orWhereHas('pessoa.responsaveis', function ($q) use ($pessoasIds) {
                    $q->whereIn('responsavel_id', $pessoasIds);
                });
            }

            // Também mantemos a lógica de alunos() vinculados diretamente às pessoas do usuário
            foreach ($user->pessoas as $pessoa) {
                $alunosIds = $pessoa->alunos()->pluck('pessoa.id')->toArray();
                if (! empty($alunosIds)) {
                    $query->orWhereIn('pessoa_id', $alunosIds);
                }
            }
        });
    }

    /**
     * Troca o professor e/ou o aluno de uma preceptoria. Tudo é conferido antes de gravar (nada fica
     * pela metade): o novo professor não pode ter outra preceptoria no mesmo horário e o aluno não
     * pode ter outra no mesmo ciclo. Quem perde ou ganha o horário é avisado, e a mudança vai para o histórico.
     *
     * @param  int|null  $matriculaId  null deixa o horário livre; só vale quando $alterarAluno é true
     * @param  bool  $alterarAluno  false mexe só no professor (quem não pode agendar não troca o aluno)
     * @return array{ok: bool, mensagem: string, aviso: string|null, notificados: int}
     */
    public function atualizarParticipantes(Preceptoria $preceptoria, ?int $professorId, ?int $matriculaId, bool $alterarAluno = true, bool $notificar = true): array
    {
        $preceptoria->loadMissing(['professor.users', 'matricula.pessoa.responsaveis.users', 'matricula.pessoa.users']);

        $professorAntes = $preceptoria->professor;
        $alunoAntes = $preceptoria->matricula?->pessoa;
        $mudouProfessor = $professorId !== null && $professorId !== (int) $preceptoria->professor_id;
        $mudouAluno = $alterarAluno && $matriculaId !== ($preceptoria->matricula_id === null ? null : (int) $preceptoria->matricula_id);

        if (! $mudouProfessor && ! $mudouAluno) {
            return $this->resultado(true, 'Nada a alterar.');
        }

        $professor = $professorAntes;
        if ($mudouProfessor) {
            $professor = Pessoa::with('users')->find($professorId);

            if (! $professor) {
                return $this->resultado(false, 'Professor não encontrado.');
            }

            $data = $preceptoria->data->toDateString();
            $conflito = $this->conflitosDoProfessor($professorId, [$data], $preceptoria->hora_inicio, $preceptoria->hora_fim, $preceptoria->getKey())[$data] ?? null;

            if ($conflito) {
                return $this->resultado(false, sprintf(
                    'Conflito: %s já tem uma preceptoria em %s das %s às %s.',
                    $professor->nome,
                    $preceptoria->data->format('d/m/Y'),
                    $conflito->hora_inicio->format('H:i'),
                    $conflito->hora_fim?->format('H:i') ?? 'fim não informado',
                ));
            }
        }

        $matricula = null;
        if ($mudouAluno && $matriculaId !== null) {
            $matricula = Matricula::with(['pessoa.responsaveis.users', 'pessoa.users'])->find($matriculaId);

            if (! $matricula) {
                return $this->resultado(false, 'Matrícula não encontrada.');
            }

            $outra = $preceptoria->ciclo_preceptoria_id
                ? Preceptoria::query()
                    ->where('matricula_id', $matriculaId)
                    ->where('ciclo_preceptoria_id', $preceptoria->ciclo_preceptoria_id)
                    ->whereKeyNot($preceptoria->getKey())
                    ->first()
                : null;

            if ($outra) {
                return $this->resultado(false, sprintf(
                    '%s já tem uma preceptoria neste ciclo (%s às %s).',
                    $matricula->pessoa?->nome ?? 'O aluno',
                    $outra->data->format('d/m/Y'),
                    $outra->hora_inicio->format('H:i'),
                ));
            }
        }

        $mensagens = [];

        DB::transaction(function () use ($preceptoria, $mudouProfessor, $mudouAluno, $professorId, $matriculaId, $professorAntes, $professor, $alunoAntes, $matricula, &$mensagens) {
            $campos = [];

            if ($mudouProfessor) {
                $campos['professor_id'] = $professorId;
                $mensagens[] = $this->registrarHistorico(
                    $preceptoria,
                    'troca_professor_preceptoria',
                    sprintf('Professor alterado de %s para %s', $professorAntes?->nome ?? 'sem professor', $professor->nome),
                    ['antes' => $professorAntes?->nome, 'depois' => $professor->nome],
                );
            }

            if ($mudouAluno) {
                $campos['matricula_id'] = $matriculaId;
                $depois = $matricula?->pessoa?->nome;
                $antes = $alunoAntes?->nome;

                [$evento, $descricao] = match (true) {
                    $antes !== null && $depois !== null => ['troca_aluno_preceptoria', "Aluno trocado de {$antes} para {$depois}"],
                    $depois !== null => ['agendamento_preceptoria', "Aluno agendado: {$depois}"],
                    default => ['liberacao_preceptoria', "Horário liberado (o aluno era {$antes})"],
                };

                $mensagens[] = $this->registrarHistorico($preceptoria, $evento, $descricao, ['antes' => $antes, 'depois' => $depois]);
            }

            $preceptoria->update($campos);
        });

        // o registro já mudou: os avisos precisam enxergar o estado novo
        $preceptoria->setRelation('professor', $professor);
        if ($mudouAluno) {
            $preceptoria->setRelation('matricula', $matricula);
        }

        $notificados = $notificar
            ? $this->avisarTroca($preceptoria, $professorAntes, $alunoAntes, $mudouProfessor, $mudouAluno)
            : 0;

        return [
            'ok' => true,
            'mensagem' => 'Preceptoria atualizada: '.implode('; ', $mensagens).'.',
            'aviso' => null,
            'notificados' => $notificados,
        ];
    }

    /**
     * Anota no histórico da preceptoria (o que aparece na tela) e devolve a descrição.
     *
     * @param  array<string, mixed>  $propriedades
     */
    public function registrarHistorico(Preceptoria $preceptoria, string $evento, string $descricao, array $propriedades = []): string
    {
        activity()
            ->performedOn($preceptoria)
            ->event($evento)
            ->withProperties($propriedades)
            ->log($descricao);

        return $descricao;
    }

    /**
     * Quem precisa saber de cada parte da troca: o professor que perdeu e o que ganhou o horário, o
     * aluno que saiu (e os responsáveis), o aluno que entrou (e os responsáveis). Quem fez a mudança não é avisado.
     */
    private function avisarTroca(Preceptoria $preceptoria, ?Pessoa $professorAntes, ?Pessoa $alunoAntes, bool $mudouProfessor, bool $mudouAluno): int
    {
        $professor = $preceptoria->professor;
        $aluno = $preceptoria->matricula?->pessoa;
        $avisos = [];

        if ($mudouProfessor) {
            // se o aluno também mudou, ele e os responsáveis recebem o aviso de agendamento/liberação
            $quem = collect([$professorAntes, $professor])->filter();
            if (! $mudouAluno) {
                $quem = $quem->concat($this->circuloDoAluno($aluno));
            }

            $avisos[] = [
                $quem,
                new PreceptoriaAlteradaNotification($preceptoria, PreceptoriaAlteradaNotification::PROFESSOR, $professorAntes?->nome ?? 'sem professor', auth()->user()?->name),
                'troca de professor',
            ];
        }

        if ($mudouAluno && $alunoAntes) {
            $quem = $this->circuloDoAluno($alunoAntes);
            // sem aluno novo e sem troca de professor, o professor é quem fica com o horário vago
            if (! $aluno && ! $mudouProfessor && $professor) {
                $quem->push($professor);
            }

            $avisos[] = [$quem, new PreceptoriaNotification($preceptoria, 'liberacao', alunoAnterior: $alunoAntes->nome), 'liberação de horário'];
        }

        if ($mudouAluno && $aluno) {
            $quem = $this->circuloDoAluno($aluno);
            if (! $mudouProfessor && $professor) {
                $quem->push($professor);
            }

            $avisos[] = [$quem, new PreceptoriaNotification($preceptoria, 'agendamento'), 'novo agendamento'];
        }

        return array_sum(array_map(
            fn (array $aviso) => $this->enviarAviso($aviso[0], $aviso[1], $preceptoria, $aviso[2]),
            $avisos,
        ));
    }

    /**
     * O aluno e seus responsáveis (com `users` carregados).
     *
     * @return Collection<int, Pessoa>
     */
    private function circuloDoAluno(?Pessoa $aluno): Collection
    {
        return $aluno ? collect([$aluno])->concat($aluno->responsaveis) : collect();
    }

    /**
     * Manda a notificação para as pessoas (e seus usuários), menos para quem está fazendo a mudança,
     * e registra no histórico quantos receberam.
     *
     * @param  Collection<int, Pessoa>  $pessoas
     * @return int quantos destinatários receberam
     */
    private function enviarAviso(Collection $pessoas, Notification $notificacao, Preceptoria $preceptoria, string $assunto): int
    {
        $autor = auth()->user();
        $enviados = 0;

        foreach (Preceptoria::destinatariosDasPessoas($pessoas) as $destinatario) {
            if ($autor && $destinatario instanceof User && $destinatario->is($autor)) {
                continue;
            }

            try {
                $destinatario->notify($notificacao);
                $enviados++;
            } catch (\Throwable $e) {
                Log::error("Erro ao avisar {$destinatario->email} ({$assunto}) na preceptoria {$preceptoria->id}: ".$e->getMessage());
            }
        }

        if ($enviados > 0) {
            $this->registrarHistorico(
                $preceptoria,
                'notificacao_alteracao_preceptoria',
                "Aviso de {$assunto} enviado para {$enviados} destinatário(s)",
                ['destinatarios_count' => $enviados],
            );
        }

        return $enviados;
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
