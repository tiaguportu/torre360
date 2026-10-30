<?php

namespace App\Services;

use App\Exceptions\ConflitoDeHorarioException;
use App\Exceptions\ExclusaoDePreceptoriaRecusadaException;
use App\Models\CicloPreceptoria;
use App\Models\CronogramaAula;
use App\Models\DiaNaoLetivo;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Preceptoria;
use App\Models\User;
use App\Notifications\Preceptorias\PreceptoriaAlteradaNotification;
use App\Notifications\Preceptorias\PreceptoriaCanceladaNotification;
use App\Notifications\Preceptorias\PreceptoriaNotification;
use Carbon\CarbonInterface;
use Closure;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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
     * Calcula a série e separa o que seria criado do que bate com outra preceptoria do professor
     * (ou do aluno, quando a série já é de um aluno). Serve tanto para a prévia do formulário
     * quanto para a criação de verdade.
     *
     * @param  array<string, mixed>  $regras  as mesmas de calcularDatas()
     * @return array{datas: array<int, string>, ignoradas: array<int, array{data: string, tipo: string, motivo: string}>, conflitos: array<string, Preceptoria>, conflitos_aluno: array<string, Preceptoria>}
     */
    public function simular(array $regras, ?int $professorId, mixed $horaInicio, mixed $horaFim = null, ?int $matriculaId = null): array
    {
        $calculo = $this->calcularDatas($regras);
        $separado = $this->separarConflitos($calculo['datas'], $professorId, $matriculaId, $horaInicio, $horaFim);

        return [
            'datas' => $separado['livres'],
            'ignoradas' => $calculo['ignoradas'],
            'conflitos' => $separado['conflitos'],
            'conflitos_aluno' => $separado['conflitos_aluno'],
        ];
    }

    /**
     * Cria a série. Datas em que o professor (ou o aluno) já tem uma preceptoria sobreposta são
     * puladas (rodar a mesma recorrência duas vezes não duplica nada) e voltam em `conflitos` e
     * `conflitos_aluno`.
     *
     * @param  array<string, mixed>  $base  ciclo_preceptoria_id, professor_id, hora_inicio, hora_fim, matricula_id
     * @param  array<string, mixed>  $regras  as mesmas de calcularDatas()
     * @return array{criadas: array<int, Preceptoria>, ignoradas: array<int, array{data: string, tipo: string, motivo: string}>, conflitos: array<string, Preceptoria>, conflitos_aluno: array<string, Preceptoria>}
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
            filled($base['matricula_id'] ?? null) ? (int) $base['matricula_id'] : null,
        );

        if (count($simulacao['datas']) > self::LIMITE_SERIE) {
            throw new DomainException(sprintf(
                'A recorrência geraria %d preceptorias; o limite por vez é %d. Reduza a janela de datas.',
                count($simulacao['datas']),
                self::LIMITE_SERIE,
            ));
        }

        return [
            'criadas' => $this->criarNasDatas($base, $simulacao['datas']),
            'ignoradas' => $simulacao['ignoradas'],
            'conflitos' => $simulacao['conflitos'],
            'conflitos_aluno' => $simulacao['conflitos_aluno'],
        ];
    }

    /**
     * Cria a mesma preceptoria em datas avulsas, pulando as em que o professor (ou o aluno) já tem
     * outra no horário.
     *
     * @param  array<string, mixed>  $base  ciclo_preceptoria_id, professor_id, hora_inicio, hora_fim, matricula_id
     * @param  array<int, string>  $datas  Y-m-d
     * @return array{criadas: array<int, Preceptoria>, ignoradas: array<int, never>, conflitos: array<string, Preceptoria>, conflitos_aluno: array<string, Preceptoria>}
     */
    public function gerarNasDatas(array $base, array $datas): array
    {
        $separado = $this->separarConflitos(
            $datas,
            isset($base['professor_id']) ? (int) $base['professor_id'] : null,
            filled($base['matricula_id'] ?? null) ? (int) $base['matricula_id'] : null,
            $base['hora_inicio'] ?? null,
            $base['hora_fim'] ?? null,
        );

        return [
            'criadas' => $this->criarNasDatas($base, $separado['livres']),
            'ignoradas' => [],
            'conflitos' => $separado['conflitos'],
            'conflitos_aluno' => $separado['conflitos_aluno'],
        ];
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
        return $this->sobreposicoes(
            Preceptoria::query()->where('professor_id', $professorId),
            $datas,
            $horaInicio,
            $horaFim,
            $ignorarId,
        );
    }

    /**
     * O mesmo para o aluno: vale a pessoa, então outra matrícula dela também conta (um aluno não
     * está em dois lugares ao mesmo tempo, seja com qual professor for).
     *
     * @param  array<int, string>  $datas  Y-m-d
     * @return array<string, Preceptoria> indexado pela data
     */
    public function conflitosDoAluno(int $matriculaId, array $datas, mixed $horaInicio, mixed $horaFim = null, ?int $ignorarId = null): array
    {
        $pessoaId = Matricula::query()->whereKey($matriculaId)->value('pessoa_id');

        if (! $pessoaId) {
            return [];
        }

        return $this->sobreposicoes(
            Preceptoria::query()->whereHas('matricula', fn (Builder $query) => $query->where('pessoa_id', $pessoaId)),
            $datas,
            $horaInicio,
            $horaFim,
            $ignorarId,
        );
    }

    /**
     * @param  Builder<Preceptoria>  $query  as preceptorias da pessoa que se confere
     * @param  array<int, string>  $datas  Y-m-d
     * @return array<string, Preceptoria> indexado pela data
     */
    private function sobreposicoes(Builder $query, array $datas, mixed $horaInicio, mixed $horaFim, ?int $ignorarId): array
    {
        if ($datas === [] || ! $horaInicio) {
            return [];
        }

        [$inicio, $fim] = $this->intervaloEmMinutos($horaInicio, $horaFim);

        $porData = $query
            ->with(['professor', 'matricula.pessoa'])
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

        $conflito = $this->conflito(
            (int) $preceptoria->professor_id,
            $preceptoria->matricula_id ? (int) $preceptoria->matricula_id : null,
            $data,
            $horaInicio,
            $horaFim,
            $preceptoria->getKey(),
        );

        if ($conflito) {
            return $this->resultado(false, $conflito->getMessage());
        }

        $antes = sprintf('%s às %s', Carbon::parse($dataAntes)->format('d/m/Y'), $horaInicioAntes ?? '—');

        try {
            $preceptoria->update([
                'data' => $data,
                'hora_inicio' => $horaInicio,
                'hora_fim' => $horaFim,
            ]);
        } catch (ConflitoDeHorarioException $e) {
            // outro usuário ocupou o horário entre a conferência e a gravação
            return $this->resultado(false, $e->getMessage());
        }

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
        }

        $matricula = null;
        if ($mudouAluno && $matriculaId !== null) {
            $matricula = Matricula::with(['pessoa.responsaveis.users', 'pessoa.users'])->find($matriculaId);

            if (! $matricula) {
                return $this->resultado(false, 'Matrícula não encontrada.');
            }
        }

        // nem o novo professor nem o novo aluno podem ter outra preceptoria que se sobreponha a esta
        $conflito = $this->conflito(
            $mudouProfessor ? $professorId : null,
            $matricula?->getKey(),
            $preceptoria->data->toDateString(),
            $preceptoria->hora_inicio,
            $preceptoria->hora_fim,
            $preceptoria->getKey(),
        );

        if ($conflito) {
            return $this->resultado(false, $conflito->getMessage());
        }

        // o aluno só faz preceptoria com os professores dele (conselheiro e cronograma da turma), como na tela de agendar
        $matriculaFinal = $mudouAluno ? $matricula : $preceptoria->matricula;

        if ($matriculaFinal && $professor && ($mudouProfessor || $mudouAluno)
            && ! $this->professoresDaMatricula($matriculaFinal->getKey())->contains('id', $professor->getKey())) {
            return $this->resultado(false, sprintf(
                '%s não é professor de %s: o aluno só pode ser agendado com o professor conselheiro ou com os professores do cronograma da turma.',
                $professor->nome,
                $matriculaFinal->pessoa?->nome ?? 'o aluno',
            ));
        }

        if ($matricula) {
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

        $gravar = function () use ($preceptoria, $mudouProfessor, $mudouAluno, $professorId, $matriculaId, $professorAntes, $professor, $alunoAntes, $matricula, &$mensagens) {
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

                [$evento, $descricao] = $this->descreverTrocaDeAluno($antes, $depois);

                $mensagens[] = $this->registrarHistorico($preceptoria, $evento, $descricao, ['antes' => $antes, 'depois' => $depois]);
            }

            $preceptoria->update($campos);
        };

        try {
            DB::transaction($gravar);
        } catch (ConflitoDeHorarioException $e) {
            // outro usuário ocupou o horário entre a conferência e a gravação; o histórico também foi desfeito
            return $this->resultado(false, $e->getMessage());
        }

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
     * Grava a edição de uma preceptoria feita fora do calendário (formulário completo, edição em lote)
     * deixando o mesmo rastro das outras telas: o que mudou (professor, aluno, horário, ciclo) entra
     * no histórico e, sendo uma preceptoria futura, quem é afetado é avisado. Se a gravação for
     * recusada por choque de horário, nada é registrado nem avisado.
     *
     * @param  array<string, mixed>  $dados  atributos a gravar
     * @param  bool  $notificar  false só registra no histórico
     * @return array{mensagens: array<int, string>, notificados: int}
     *
     * @throws ConflitoDeHorarioException
     */
    public function atualizarComHistorico(Preceptoria $preceptoria, array $dados, bool $notificar = true): array
    {
        $antes = $this->retrato($preceptoria, doBanco: true);
        $alteracoes = null;

        // a gravação e o histórico andam juntos: se um falhar, o outro não fica
        DB::transaction(function () use ($preceptoria, $dados, $antes, &$alteracoes) {
            $preceptoria->update($dados);

            $alteracoes = $this->registrarAlteracoes($preceptoria, $antes);
        });

        if ($alteracoes === null) {
            return ['mensagens' => [], 'notificados' => 0];
        }

        // corrigir o cadastro de uma preceptoria que já passou não vira aviso para ninguém
        $notificados = $notificar && $preceptoria->isAgendamentoFuturo()
            ? $this->avisarAlteracoes($preceptoria, $alteracoes)
            : 0;

        return ['mensagens' => $alteracoes['mensagens'], 'notificados' => $notificados];
    }

    /** O que o aviso de cancelamento e o de restauração precisam enxergar para dizer quem são os envolvidos. */
    private const RELACOES_DO_AVISO = ['professor.users', 'matricula.pessoa.responsaveis.users', 'matricula.pessoa.users'];

    /**
     * O que acontece se essas preceptorias forem excluídas: quantas serão recusadas (por terem relatório)
     * e quantos destinatários receberão o aviso de cancelamento (só as futuras que têm aluno avisam).
     *
     * @param  iterable<int, Preceptoria>  $preceptorias
     * @return array{total: int, recusadas: int, avisados: int}
     */
    public function previaDaExclusao(iterable $preceptorias): array
    {
        $preceptorias = new EloquentCollection(collect($preceptorias)->all());
        $preceptorias->loadMissing(self::RELACOES_DO_AVISO);

        $comRelatorio = Preceptoria::query()
            ->whereKey($preceptorias->modelKeys())
            ->whereHas('relatorios')
            ->pluck('id');

        $avisados = $preceptorias
            ->reject(fn (Preceptoria $preceptoria) => $comRelatorio->contains($preceptoria->getKey()))
            ->sum(fn (Preceptoria $preceptoria) => $this->destinatariosDoCancelamento($preceptoria)->count());

        return ['total' => $preceptorias->count(), 'recusadas' => $comRelatorio->count(), 'avisados' => $avisados];
    }

    /**
     * Exclui a preceptoria (o registro fica guardado e pode ser restaurado) deixando o rastro no
     * histórico: o que era, quem excluiu e quando. Sendo uma preceptoria futura com aluno, o professor, o
     * aluno e os responsáveis são avisados do cancelamento (quem excluiu não recebe).
     *
     * Preceptoria com relatório não é excluída: o relatório é o registro do encontro. Para devolver o
     * horário à agenda, basta liberar o aluno.
     *
     * @param  bool  $notificar  false só registra no histórico
     * @return array{mensagem: string, notificados: int}
     *
     * @throws ExclusaoDePreceptoriaRecusadaException
     */
    public function excluirComHistorico(Preceptoria $preceptoria, bool $notificar = true): array
    {
        $preceptoria->unsetRelation('professor')->unsetRelation('matricula')->unsetRelation('cicloPreceptoria');
        $preceptoria->load([...self::RELACOES_DO_AVISO, 'cicloPreceptoria']);

        $retrato = $this->retrato($preceptoria);
        $horario = $this->rotuloHorario($retrato);
        $professor = $preceptoria->professor?->nome;
        $aluno = $preceptoria->matricula?->pessoa?->nome;
        $destinatarios = $notificar ? $this->destinatariosDoCancelamento($preceptoria) : collect();

        $detalhe = ($aluno ? "aluno: {$aluno}" : 'horário livre').'; professor: '.($professor ?? 'sem professor');
        $descricao = "Preceptoria de {$horario} excluída ({$detalhe})";

        // a exclusão e o histórico andam juntos; o relatório é conferido dentro, com o registro já lido
        DB::transaction(function () use ($preceptoria, $descricao, $horario, $professor, $aluno) {
            $relatorios = $preceptoria->relatorios()->count();

            if ($relatorios > 0) {
                throw ExclusaoDePreceptoriaRecusadaException::comRelatorios($preceptoria, $relatorios);
            }

            $this->registrarHistorico($preceptoria, 'exclusao_preceptoria', $descricao, [
                'horario' => $horario,
                'professor' => $professor,
                'aluno' => $aluno,
                'ciclo' => $preceptoria->cicloPreceptoria?->nome,
            ]);

            $preceptoria->delete();
        });

        $notificados = $destinatarios->isEmpty()
            ? 0
            : $this->enviarAviso(
                collect([$preceptoria->professor])->filter()->concat($this->circuloDoAluno($preceptoria->matricula?->pessoa)),
                new PreceptoriaCanceladaNotification(
                    $preceptoria->data->format('d/m/Y'),
                    $this->horasDaPreceptoria($retrato),
                    $professor,
                    $aluno,
                    auth()->user()?->name,
                ),
                $preceptoria,
                'cancelamento',
            );

        return ['mensagem' => $descricao, 'notificados' => $notificados];
    }

    /**
     * Devolve à agenda uma preceptoria excluída, desde que o professor e o aluno continuem livres no
     * horário dela (nesse tempo podem ter marcado outra). Sendo futura e com aluno, os envolvidos são
     * avisados de que o horário voltou.
     *
     * @return int quantos destinatários foram avisados
     *
     * @throws ConflitoDeHorarioException
     */
    public function restaurarComHistorico(Preceptoria $preceptoria, bool $notificar = true): int
    {
        DB::transaction(function () use ($preceptoria) {
            $this->travarEConferir(
                $preceptoria,
                $preceptoria->professor_id ? (int) $preceptoria->professor_id : null,
                $preceptoria->matricula_id ? (int) $preceptoria->matricula_id : null,
            );

            $preceptoria->restore();

            $this->registrarHistorico(
                $preceptoria,
                'restauracao_preceptoria',
                'Preceptoria restaurada: '.$this->rotuloHorario($this->retrato($preceptoria)),
            );
        });

        $preceptoria->unsetRelation('professor')->unsetRelation('matricula');
        $preceptoria->load(self::RELACOES_DO_AVISO);

        if (! $notificar || $this->destinatariosDoCancelamento($preceptoria)->isEmpty()) {
            return 0;
        }

        return $this->enviarAviso(
            collect([$preceptoria->professor])->filter()->concat($this->circuloDoAluno($preceptoria->matricula?->pessoa)),
            new PreceptoriaNotification($preceptoria, 'agendamento'),
            $preceptoria,
            'restauração',
        );
    }

    /**
     * Quem recebe o aviso de cancelamento (ou de volta do horário): só quando a preceptoria é futura e tem
     * aluno; horário livre ou que já passou não avisa ninguém. Exige as relações de RELACOES_DO_AVISO.
     */
    private function destinatariosDoCancelamento(Preceptoria $preceptoria): Collection
    {
        if (! $preceptoria->matricula_id || ! $preceptoria->isAgendamentoFuturo()) {
            return collect();
        }

        return $this->destinatariosSemAutor(
            collect([$preceptoria->professor])->filter()->concat($this->circuloDoAluno($preceptoria->matricula?->pessoa)),
        );
    }

    /**
     * "07:15" ou, com término, "07:15 às 07:45".
     *
     * @param  array<string, mixed>  $retrato
     */
    private function horasDaPreceptoria(array $retrato): string
    {
        $inicio = $retrato['hora_inicio'] ?? '—';

        return $retrato['hora_fim'] ? "{$inicio} às {$retrato['hora_fim']}" : $inicio;
    }

    /**
     * O que interessa ao histórico de uma preceptoria, em valores comparáveis (ids como inteiros,
     * data e horas como texto), para dizer depois de uma gravação o que mudou.
     *
     * $doBanco lê o que está gravado, ignorando o que já foi atribuído ao model e ainda não salvo: o
     * seletor de relação do Filament (professor, aluno, ciclo) associa a chave estrangeira ao model
     * antes de a página chamar a gravação, então o valor "atual" dele já é o novo.
     *
     * @return array{professor_id: int|null, matricula_id: int|null, ciclo_preceptoria_id: int|null, data: string|null, hora_inicio: string|null, hora_fim: string|null}
     */
    private function retrato(Preceptoria $preceptoria, bool $doBanco = false): array
    {
        $valor = fn (string $campo) => $doBanco ? $preceptoria->getOriginal($campo) : $preceptoria->getAttribute($campo);
        $id = fn (string $campo) => $valor($campo) ? (int) $valor($campo) : null;

        return [
            'professor_id' => $id('professor_id'),
            'matricula_id' => $id('matricula_id'),
            'ciclo_preceptoria_id' => $id('ciclo_preceptoria_id'),
            'data' => $valor('data')?->toDateString(),
            'hora_inicio' => $valor('hora_inicio')?->format('H:i'),
            'hora_fim' => $valor('hora_fim')?->format('H:i'),
        ];
    }

    /**
     * Compara o registro já gravado com o retrato de antes e anota cada mudança no histórico.
     *
     * @param  array<string, mixed>  $antes  o retrato tirado antes da gravação
     * @return array{professor: bool, aluno: bool, horario: bool, ciclo: bool, professorAntes: Pessoa|null, alunoAntes: Pessoa|null, horarioAntes: string, mensagens: array<int, string>}|null null quando nada mudou
     */
    private function registrarAlteracoes(Preceptoria $preceptoria, array $antes): ?array
    {
        $depois = $this->retrato($preceptoria);
        $camposDoHorario = ['data', 'hora_inicio', 'hora_fim'];

        $mudouProfessor = $antes['professor_id'] !== $depois['professor_id'];
        $mudouAluno = $antes['matricula_id'] !== $depois['matricula_id'];
        $mudouCiclo = $antes['ciclo_preceptoria_id'] !== $depois['ciclo_preceptoria_id'];
        $mudouHorario = Arr::only($antes, $camposDoHorario) !== Arr::only($depois, $camposDoHorario);

        if (! $mudouProfessor && ! $mudouAluno && ! $mudouCiclo && ! $mudouHorario) {
            return null;
        }

        // quem e o que era antes: o registro já mudou, então vem do banco (só o que mudou)
        $professorAntes = $mudouProfessor && $antes['professor_id']
            ? Pessoa::with('users')->find($antes['professor_id'])
            : null;
        $alunoAntes = $mudouAluno && $antes['matricula_id']
            ? Matricula::with(['pessoa.responsaveis.users', 'pessoa.users'])->find($antes['matricula_id'])?->pessoa
            : null;
        $cicloAntes = $mudouCiclo && $antes['ciclo_preceptoria_id']
            ? CicloPreceptoria::find($antes['ciclo_preceptoria_id'])
            : null;

        // as relações que a listagem já carregou ainda são as de antes da gravação
        $preceptoria->unsetRelation('professor')->unsetRelation('matricula')->unsetRelation('cicloPreceptoria');
        $preceptoria->load(['professor.users', 'matricula.pessoa.responsaveis.users', 'matricula.pessoa.users', 'cicloPreceptoria']);

        $mensagens = [];
        $horarioAntes = $this->rotuloHorario($antes);

        if ($mudouProfessor) {
            $de = $professorAntes?->nome ?? 'sem professor';
            $para = $preceptoria->professor?->nome ?? 'sem professor';

            $mensagens[] = $this->registrarHistorico($preceptoria, 'troca_professor_preceptoria', "Professor alterado de {$de} para {$para}", ['antes' => $de, 'depois' => $para]);
        }

        if ($mudouAluno) {
            $de = $alunoAntes?->nome;
            $para = $preceptoria->matricula?->pessoa?->nome;
            [$evento, $descricao] = $this->descreverTrocaDeAluno($de, $para);

            $mensagens[] = $this->registrarHistorico($preceptoria, $evento, $descricao, ['antes' => $de, 'depois' => $para]);
        }

        if ($mudouHorario) {
            $para = $this->rotuloHorario($depois);

            $mensagens[] = $this->registrarHistorico($preceptoria, 'reagendamento_preceptoria', "Preceptoria reagendada de {$horarioAntes} para {$para}", ['antes' => $horarioAntes, 'depois' => $para]);
        }

        if ($mudouCiclo) {
            $de = $cicloAntes?->nome ?? 'sem ciclo';
            $para = $preceptoria->cicloPreceptoria?->nome ?? 'sem ciclo';

            $mensagens[] = $this->registrarHistorico($preceptoria, 'troca_ciclo_preceptoria', "Ciclo alterado de {$de} para {$para}", ['antes' => $de, 'depois' => $para]);
        }

        return [
            'professor' => $mudouProfessor,
            'aluno' => $mudouAluno,
            'horario' => $mudouHorario,
            'ciclo' => $mudouCiclo,
            'professorAntes' => $professorAntes,
            'alunoAntes' => $alunoAntes,
            'horarioAntes' => $horarioAntes,
            'mensagens' => $mensagens,
        ];
    }

    /**
     * Avisa o que mudou (a troca de ciclo é só do histórico). Mudando o horário junto com o professor
     * ou o aluno, o aviso da troca já mostra o horário novo e, no do professor, o anterior; o aviso de
     * reagendamento é só para quando o horário foi a única mudança que afeta as pessoas.
     *
     * @param  array{professor: bool, aluno: bool, horario: bool, professorAntes: Pessoa|null, alunoAntes: Pessoa|null, horarioAntes: string}  $alteracoes
     */
    private function avisarAlteracoes(Preceptoria $preceptoria, array $alteracoes): int
    {
        $avisados = $this->avisarTroca(
            $preceptoria,
            $alteracoes['professorAntes'],
            $alteracoes['alunoAntes'],
            $alteracoes['professor'],
            $alteracoes['aluno'],
            $alteracoes['horario'] ? $alteracoes['horarioAntes'] : null,
        );

        if ($alteracoes['horario'] && ! $alteracoes['professor'] && ! $alteracoes['aluno']) {
            $avisados += $this->enviarAviso(
                collect([$preceptoria->professor])->filter()->concat($this->circuloDoAluno($preceptoria->matricula?->pessoa)),
                new PreceptoriaAlteradaNotification($preceptoria, PreceptoriaAlteradaNotification::HORARIO, $alteracoes['horarioAntes'], auth()->user()?->name),
                $preceptoria,
                'reagendamento',
            );
        }

        return $avisados;
    }

    /**
     * "24/08/2026 às 07:15" ou, com término, "24/08/2026 das 07:15 às 07:45".
     *
     * @param  array<string, mixed>  $retrato
     */
    private function rotuloHorario(array $retrato): string
    {
        $data = $retrato['data'] ? Carbon::parse($retrato['data'])->format('d/m/Y') : 'sem data';
        $inicio = $retrato['hora_inicio'] ?? '—';

        return $retrato['hora_fim']
            ? "{$data} das {$inicio} às {$retrato['hora_fim']}"
            : "{$data} às {$inicio}";
    }

    /**
     * O evento e o texto do histórico para o aluno que saiu e/ou entrou na preceptoria.
     *
     * @return array{0: string, 1: string}
     */
    private function descreverTrocaDeAluno(?string $antes, ?string $depois): array
    {
        return match (true) {
            $antes !== null && $depois !== null => ['troca_aluno_preceptoria', "Aluno trocado de {$antes} para {$depois}"],
            $depois !== null => ['agendamento_preceptoria', "Aluno agendado: {$depois}"],
            default => ['liberacao_preceptoria', "Horário liberado (o aluno era {$antes})"],
        };
    }

    /**
     * Os professores do aluno: o conselheiro da turma e quem está no cronograma dela. É com eles
     * que a preceptoria do aluno pode ser marcada.
     *
     * @return Collection<int, Pessoa>
     */
    public function professoresDaMatricula(int $matriculaId): Collection
    {
        $matricula = Matricula::with(['turma'])->find($matriculaId);

        if (! $matricula || ! $matricula->turma) {
            return collect();
        }

        $professoresIds = collect();

        if ($matricula->turma->professor_conselheiro_id) {
            $professoresIds->push($matricula->turma->professor_conselheiro_id);
        }

        $cronogramaProfessoresIds = CronogramaAula::where('turma_id', $matricula->turma_id)
            ->whereNotNull('pessoa_id')
            ->pluck('pessoa_id');

        $professoresIds = $professoresIds->concat($cronogramaProfessoresIds)->unique();

        if ($professoresIds->isEmpty()) {
            return collect();
        }

        return Pessoa::whereIn('id', $professoresIds)->get();
    }

    /**
     * Só as matrículas cujo aluno tem o professor entre os seus (o inverso de professoresDaMatricula).
     *
     * @param  Builder<Matricula>  $matriculas
     * @return Builder<Matricula>
     */
    public function matriculasDoProfessor(Builder $matriculas, int $professorId): Builder
    {
        return $matriculas->where(function (Builder $query) use ($professorId) {
            $query->whereHas('turma', fn (Builder $turma) => $turma->where('professor_conselheiro_id', $professorId))
                ->orWhereIn('turma_id', CronogramaAula::query()->where('pessoa_id', $professorId)->select('turma_id'));
        });
    }

    /**
     * Grava a preceptoria só se o professor e o aluno não tiverem outra no mesmo horário. A conferência
     * e a gravação acontecem juntas, com a linha das pessoas travada, para dois usuários marcando o
     * mesmo professor (ou aluno) ao mesmo tempo não passarem os dois pela conferência.
     *
     * Só confere o que mudou: liberar o aluno de um horário, por exemplo, não reabre a conferência do
     * professor (uma sobreposição antiga não impede o ajuste).
     *
     * @param  Closure(): bool  $gravar
     *
     * @throws ConflitoDeHorarioException
     */
    public function gravarSemConflito(Preceptoria $preceptoria, Closure $gravar): bool
    {
        $mudouHorario = ! $preceptoria->exists || $preceptoria->isDirty(['data', 'hora_inicio', 'hora_fim']);
        $temHorario = $preceptoria->data && $preceptoria->hora_inicio;

        $professorId = $temHorario && $preceptoria->professor_id && ($mudouHorario || $preceptoria->isDirty('professor_id'))
            ? (int) $preceptoria->professor_id
            : null;
        $matriculaId = $temHorario && $preceptoria->matricula_id && ($mudouHorario || $preceptoria->isDirty('matricula_id'))
            ? (int) $preceptoria->matricula_id
            : null;

        if ($professorId === null && $matriculaId === null) {
            return $gravar();
        }

        return DB::transaction(function () use ($preceptoria, $professorId, $matriculaId, $gravar) {
            $this->travarEConferir($preceptoria, $professorId, $matriculaId);

            return $gravar();
        });
    }

    /**
     * Trava a linha das pessoas (sempre na ordem do id, para duas gravações cruzadas não travarem uma à
     * outra) e confere se o professor e o aluno estão livres no horário da preceptoria. Só vale dentro de
     * uma transação.
     *
     * @throws ConflitoDeHorarioException
     */
    private function travarEConferir(Preceptoria $preceptoria, ?int $professorId, ?int $matriculaId): void
    {
        $pessoaDoAluno = $matriculaId ? Matricula::query()->whereKey($matriculaId)->value('pessoa_id') : null;

        Pessoa::query()
            ->whereIn('id', array_filter([$professorId, $pessoaDoAluno]))
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id');

        $conflito = $this->conflito(
            $professorId,
            $matriculaId,
            $preceptoria->data->toDateString(),
            $preceptoria->hora_inicio,
            $preceptoria->hora_fim,
            $preceptoria->exists ? $preceptoria->getKey() : null,
        );

        if ($conflito) {
            throw $conflito;
        }
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
    private function avisarTroca(Preceptoria $preceptoria, ?Pessoa $professorAntes, ?Pessoa $alunoAntes, bool $mudouProfessor, bool $mudouAluno, ?string $horarioAnterior = null): int
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
                new PreceptoriaAlteradaNotification($preceptoria, PreceptoriaAlteradaNotification::PROFESSOR, $professorAntes?->nome ?? 'sem professor', auth()->user()?->name, $horarioAnterior),
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
        $enviados = 0;

        foreach ($this->destinatariosSemAutor($pessoas) as $destinatario) {
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
     * Quem de fato recebe o aviso para essas pessoas (a própria pessoa e seus usuários), menos quem está
     * fazendo a mudança.
     *
     * @param  Collection<int, Pessoa>  $pessoas
     */
    private function destinatariosSemAutor(Collection $pessoas): Collection
    {
        $autor = auth()->user();

        return Preceptoria::destinatariosDasPessoas($pessoas)
            ->reject(fn ($destinatario) => $autor && $destinatario instanceof User && $destinatario->is($autor))
            ->values();
    }

    /**
     * O primeiro choque de horário numa data: primeiro o do professor, depois o do aluno. Cada lado
     * só é conferido se o id foi informado.
     */
    private function conflito(?int $professorId, ?int $matriculaId, string $data, mixed $horaInicio, mixed $horaFim, ?int $ignorarId): ?ConflitoDeHorarioException
    {
        if ($professorId && ($outra = $this->conflitosDoProfessor($professorId, [$data], $horaInicio, $horaFim, $ignorarId)[$data] ?? null)) {
            return ConflitoDeHorarioException::doProfessor($outra);
        }

        if ($matriculaId && ($outra = $this->conflitosDoAluno($matriculaId, [$data], $horaInicio, $horaFim, $ignorarId)[$data] ?? null)) {
            return ConflitoDeHorarioException::doAluno($outra);
        }

        return null;
    }

    /**
     * Separa as datas livres das que batem com outra preceptoria do professor ou do aluno.
     *
     * @param  array<int, string>  $datas  Y-m-d
     * @return array{livres: array<int, string>, conflitos: array<string, Preceptoria>, conflitos_aluno: array<string, Preceptoria>}
     */
    private function separarConflitos(array $datas, ?int $professorId, ?int $matriculaId, mixed $horaInicio, mixed $horaFim): array
    {
        $conflitos = $professorId && $horaInicio
            ? $this->conflitosDoProfessor($professorId, $datas, $horaInicio, $horaFim)
            : [];
        $conflitosAluno = $matriculaId && $horaInicio
            ? $this->conflitosDoAluno($matriculaId, $datas, $horaInicio, $horaFim)
            : [];

        return [
            'livres' => array_values(array_filter($datas, fn (string $data) => ! isset($conflitos[$data]) && ! isset($conflitosAluno[$data]))),
            'conflitos' => $conflitos,
            'conflitos_aluno' => $conflitosAluno,
        ];
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<int, string>  $datas  Y-m-d
     * @return array<int, Preceptoria>
     */
    private function criarNasDatas(array $base, array $datas): array
    {
        $campos = Arr::only($base, ['ciclo_preceptoria_id', 'professor_id', 'matricula_id', 'hora_inicio', 'hora_fim']);

        return DB::transaction(fn () => array_map(
            fn (string $data) => Preceptoria::create([...$campos, 'data' => $data]),
            $datas,
        ));
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
