<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StatusVisitaInteressado;
use App\Models\DiaNaoLetivo;
use App\Models\DisponibilidadeVisita;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\ReguaFollowUp;
use App\Models\User;
use App\Models\VisitaInteressado;
use App\Notifications\VisitaOnlineNotification;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Agenda de visitas à escola marcada pela própria família (Onda 86, sem a distribuição da Onda 85).
 *
 * O horário oferecido sai da disponibilidade publicada pelos consultores, sem os dias não letivos, sem o que já está ocupado e
 * respeitando a antecedência mínima e a máxima. Quando mais de um consultor atende no mesmo horário, a visita fica com quem
 * tem menos visitas no dia (empate: o mais antigo). Nenhuma regra de carteira ou de rodízio entra aqui.
 */
class AgendaVisitaService
{
    public function __construct(private readonly CaptacaoInteressadoService $captacao) {}

    /**
     * Horários livres por dia, no formato `['2026-10-26' => ['09:00' => [consultorId, ...], ...]]`.
     *
     * @return array<string, array<string, list<int>>>
     */
    public function horariosLivres(?int $unidadeId = null, ?CarbonInterface $agora = null, ?int $limiteDias = null): array
    {
        $agora = $agora ? Carbon::instance($agora) : now();

        $regras = DisponibilidadeVisita::query()
            ->where('ativo', true)
            ->whereHas('consultor', fn ($q) => $q->ativos())
            ->when($unidadeId, fn ($q) => $q->where(fn ($w) => $w->whereNull('unidade_id')->orWhere('unidade_id', $unidadeId)))
            ->get();

        if ($regras->isEmpty()) {
            return [];
        }

        $diasAFrente = min(
            $limiteDias ?? (int) config('visitas.dias_a_exibir', 21),
            (int) $regras->max('antecedencia_maxima_dias'),
        );
        $primeiroDia = $agora->copy()->startOfDay();
        $ultimoDia = $primeiroDia->copy()->addDays($diasAFrente);

        $bloqueados = DiaNaoLetivo::query()
            ->where('flag_ativo', true)
            ->whereBetween('data', [$primeiroDia->toDateString(), $ultimoDia->toDateString()])
            ->pluck('data')
            ->map(fn ($data): string => Carbon::parse($data)->toDateString())
            ->flip();

        $ocupadas = VisitaInteressado::query()
            ->agendadas()
            ->whereIn('usuario_id', $regras->pluck('user_id')->unique())
            ->whereBetween('data_hora', [$primeiroDia, $ultimoDia->copy()->endOfDay()])
            ->get(['usuario_id', 'data_hora'])
            ->groupBy('usuario_id');

        $livres = [];

        for ($dia = $primeiroDia->copy(); $dia->lte($ultimoDia); $dia->addDay()) {
            if ($bloqueados->has($dia->toDateString())) {
                continue;
            }

            foreach ($regras->where('dia_semana', $dia->dayOfWeekIso) as $regra) {
                $minimo = $agora->copy()->addHours($regra->antecedencia_minima_horas);
                $maximo = $agora->copy()->addDays($regra->antecedencia_maxima_dias);
                $fim = Carbon::parse($dia->toDateString().' '.$regra->hora_fim);

                for ($inicio = Carbon::parse($dia->toDateString().' '.$regra->hora_inicio); $inicio->copy()->addMinutes($regra->duracao_minutos)->lte($fim); $inicio->addMinutes($regra->duracao_minutos)) {
                    if ($inicio->lt($minimo) || $inicio->gt($maximo)) {
                        continue;
                    }

                    if ($this->haConflito($ocupadas->get($regra->user_id), $inicio, $regra->duracao_minutos)) {
                        continue;
                    }

                    $livres[$dia->toDateString()][$inicio->format('H:i')][] = $regra->user_id;
                }
            }
        }

        foreach ($livres as $data => $horarios) {
            ksort($horarios);
            $livres[$data] = array_map(fn (array $ids): array => array_values(array_unique($ids)), $horarios);
        }

        ksort($livres);

        return $livres;
    }

    /**
     * Marca a visita: confere o horário de novo (dentro de uma trava por horário), registra o lead pelas mesmas regras do
     * formulário público, cria a visita e avisa a família e o consultor.
     *
     * @param  array{data_hora: string, unidade_id?: ?int, responsavel_nome: string, responsavel_email: string, responsavel_telefone: string, aluno_nome: string, serie_id?: ?int, turno_preferencia?: ?string}  $dados
     *
     * @throws \DomainException horário que não está mais livre
     */
    public function reservar(array $dados, Request $request): VisitaInteressado
    {
        $inicio = Carbon::createFromFormat('Y-m-d H:i', $dados['data_hora']);
        $unidadeId = $dados['unidade_id'] ?? null;

        $visita = $this->comTravaDoHorario($inicio, function () use ($dados, $request, $inicio, $unidadeId): VisitaInteressado {
            return DB::transaction(function () use ($dados, $request, $inicio, $unidadeId): VisitaInteressado {
                $consultorId = $this->escolherConsultor($unidadeId, $inicio);

                $resultado = $this->captacao->registrar([
                    'tipo_preenchimento' => 'responsavel',
                    'responsavel_nome' => $dados['responsavel_nome'],
                    'responsavel_email' => $dados['responsavel_email'],
                    'responsavel_telefone' => $dados['responsavel_telefone'],
                    'alunos' => [array_filter([
                        'nome' => $dados['aluno_nome'],
                        'serie_id' => $dados['serie_id'] ?? null,
                        'unidade_id' => $unidadeId,
                        'turno_preferencia' => $dados['turno_preferencia'] ?? null,
                    ], fn ($valor) => filled($valor))],
                    'observacoes' => 'Visita marcada pela página pública de agendamento.',
                ], $request);

                /** @var Interessado $interessado */
                $interessado = $resultado['interessado'];

                // Lead novo e sem dono fica com o consultor da visita; o de quem já tem dono continua com ele.
                if ($resultado['novo'] && ! $interessado->usuario_id) {
                    $interessado->update(['usuario_id' => $consultorId]);
                }

                $nomeNormalizado = InteressadoDependente::nomeNormalizado($dados['aluno_nome']);
                $dependente = $interessado->dependentes()->get()
                    ->first(fn (InteressadoDependente $d): bool => InteressadoDependente::nomeNormalizado($d->nome_crianca) === $nomeNormalizado);

                $visita = VisitaInteressadoService::agendar($interessado, $inicio, $consultorId, $dependente?->id, 'Marcada pela família na página pública.');
                $visita->forceFill([
                    'token_agendamento' => Str::random(48),
                    'origem' => 'online',
                    'unidade_id' => $unidadeId,
                ])->save();

                return $visita;
            });
        });

        $this->avisar($visita, VisitaOnlineNotification::CONFIRMADA);
        $this->avisarConsultor($visita, 'Nova visita marcada pela família', "{$visita->interessado->pessoa->nome} marcou uma visita para {$visita->data_hora->format('d/m/Y \à\s H:i')}.");

        return $visita;
    }

    public function buscarPorToken(string $token): ?VisitaInteressado
    {
        if (strlen($token) < 40) {
            return null;
        }

        return VisitaInteressado::query()->where('token_agendamento', $token)->with(['interessado.pessoa', 'unidade', 'usuario'])->first();
    }

    /**
     * Cancelamento pela família. Avisa o consultor sempre, e em destaque se faltam poucas horas.
     *
     * @throws \DomainException
     */
    public function cancelar(VisitaInteressado $visita): VisitaInteressado
    {
        $this->garantirQueAindaPodeMudar($visita);

        $pertoDaVisita = $visita->data_hora->lt(now()->addHours((int) config('visitas.aviso_cancelamento_horas', 4)));

        $visita->update(['status' => StatusVisitaInteressado::Cancelada]);

        $this->avisar($visita, VisitaOnlineNotification::CANCELADA);
        $this->avisarConsultor(
            $visita,
            $pertoDaVisita ? 'Visita cancelada em cima da hora' : 'Visita cancelada pela família',
            "{$visita->interessado->pessoa->nome} cancelou a visita de {$visita->data_hora->format('d/m/Y \à\s H:i')}.",
        );

        return $visita;
    }

    /**
     * Troca o horário. O consultor atual é mantido se estiver livre no novo horário; senão vale a mesma escolha de uma visita
     * nova. O horário antigo volta a ser oferecido na hora.
     *
     * @throws \DomainException
     */
    public function reagendar(VisitaInteressado $visita, string $novaDataHora): VisitaInteressado
    {
        $this->garantirQueAindaPodeMudar($visita);

        $inicio = Carbon::createFromFormat('Y-m-d H:i', $novaDataHora);
        $anterior = $visita->data_hora->copy();

        $this->comTravaDoHorario($inicio, function () use ($visita, $inicio): void {
            $livres = $this->horariosLivres($visita->unidade_id)[$inicio->toDateString()][$inicio->format('H:i')] ?? [];

            if ($livres === []) {
                throw new \DomainException('Esse horário acabou de ser ocupado ou não está mais disponível. Escolha outro.');
            }

            $consultorId = in_array($visita->usuario_id, $livres, true) ? $visita->usuario_id : $this->escolherConsultor($visita->unidade_id, $inicio);

            $visita->forceFill([
                'data_hora' => $inicio,
                'usuario_id' => $consultorId,
                'lembrete_enviado_em' => null,
                'lembrete_familia_enviado_em' => null,
            ])->save();
        });

        VisitaInteressadoService::sincronizarProximoContato($visita->interessado);

        $this->avisar($visita->refresh(), VisitaOnlineNotification::REAGENDADA);
        $this->avisarConsultor($visita, 'Visita reagendada pela família', "{$visita->interessado->pessoa->nome} trocou a visita de {$anterior->format('d/m/Y \à\s H:i')} para {$visita->data_hora->format('d/m/Y \à\s H:i')}.");

        return $visita;
    }

    /**
     * Lembrete à família das visitas online das próximas 24 horas (e-mail). Existe só quando a escola não configurou uma regra
     * ativa de lembrete de visita na régua de follow-up, para a família não receber dois avisos.
     *
     * @return int quantidade de lembretes enviados
     */
    public function enviarLembretesDaFamilia(): int
    {
        if (ReguaFollowUp::query()->where('gatilho', 'visita_lembrete')->where('is_ativo', true)->exists()) {
            return 0;
        }

        $enviados = 0;

        VisitaInteressado::query()
            ->agendadas()
            ->where('origem', 'online')
            ->whereNull('lembrete_familia_enviado_em')
            ->whereBetween('data_hora', [now(), now()->addHours(VisitaInteressadoService::JANELA_LEMBRETE_HORAS)])
            ->with(['interessado.pessoa', 'unidade'])
            ->each(function (VisitaInteressado $visita) use (&$enviados): void {
                $visita->forceFill(['lembrete_familia_enviado_em' => now()])->saveQuietly();

                if ($this->avisar($visita, VisitaOnlineNotification::LEMBRETE)) {
                    $enviados++;
                }
            });

        return $enviados;
    }

    public function urlDaVisita(VisitaInteressado $visita): string
    {
        return route('visita.publica.mostrar', ['token' => $visita->token_agendamento]);
    }

    /**
     * Quem atende naquele horário: o consultor com menos visitas marcadas no dia (empate: o de menor id).
     *
     * @throws \DomainException horário que não está livre
     */
    private function escolherConsultor(?int $unidadeId, CarbonInterface $inicio): int
    {
        $candidatos = $this->horariosLivres($unidadeId, null, 365)[$inicio->toDateString()][$inicio->format('H:i')] ?? [];

        if ($candidatos === []) {
            throw new \DomainException('Esse horário acabou de ser ocupado ou não está mais disponível. Escolha outro.');
        }

        $doDia = VisitaInteressado::query()
            ->agendadas()
            ->whereIn('usuario_id', $candidatos)
            ->whereDate('data_hora', $inicio->toDateString())
            ->selectRaw('usuario_id, count(*) as total')
            ->groupBy('usuario_id')
            ->pluck('total', 'usuario_id');

        usort($candidatos, fn (int $a, int $b): int => [$doDia->get($a, 0), $a] <=> [$doDia->get($b, 0), $b]);

        return $candidatos[0];
    }

    private function garantirQueAindaPodeMudar(VisitaInteressado $visita): void
    {
        if ($visita->status !== StatusVisitaInteressado::Agendada) {
            throw new \DomainException('Esta visita não está mais agendada.');
        }

        if ($visita->data_hora->isPast()) {
            throw new \DomainException('A data da visita já passou.');
        }
    }

    /**
     * @param  array<int, VisitaInteressado>|Collection<int, VisitaInteressado>|null  $visitasDoConsultor
     */
    private function haConflito($visitasDoConsultor, CarbonInterface $inicio, int $duracaoMinutos): bool
    {
        if (! $visitasDoConsultor) {
            return false;
        }

        $fim = $inicio->copy()->addMinutes($duracaoMinutos);

        foreach ($visitasDoConsultor as $visita) {
            $visitaFim = $visita->data_hora->copy()->addMinutes($duracaoMinutos);

            if ($visita->data_hora->lt($fim) && $visitaFim->gt($inicio)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Serializa as marcações do mesmo horário: duas famílias no mesmo minuto não pegam o mesmo consultor.
     *
     * @template T
     *
     * @param  callable(): T  $acao
     * @return T
     *
     * @throws \DomainException
     */
    private function comTravaDoHorario(CarbonInterface $inicio, callable $acao): mixed
    {
        $trava = Cache::lock('visita-horario:'.$inicio->format('YmdHi'), 15);

        try {
            $trava->block(5);
        } catch (LockTimeoutException) {
            throw new \DomainException('Muita gente marcando neste horário. Tente de novo em instantes.');
        }

        try {
            return $acao();
        } finally {
            $trava->release();
        }
    }

    private function avisar(VisitaInteressado $visita, string $evento): bool
    {
        $visita->loadMissing('interessado.pessoa');
        $email = $visita->interessado?->pessoa?->email;

        if (blank($email)) {
            return false;
        }

        Notification::route('mail', $email)->notify(new VisitaOnlineNotification($visita->id, $evento));

        return true;
    }

    private function avisarConsultor(VisitaInteressado $visita, string $titulo, string $corpo): void
    {
        $consultor = $visita->usuario ?? User::find($visita->usuario_id);

        if (! $consultor) {
            return;
        }

        FilamentNotification::make()
            ->title($titulo)
            ->body($corpo)
            ->icon('heroicon-o-calendar-days')
            ->color('info')
            ->sendToDatabase($consultor);
    }
}
