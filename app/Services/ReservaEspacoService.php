<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StatusReservaEspaco;
use App\Exceptions\ReservaEspacoIndisponivelException;
use App\Models\ReservaEspaco;
use App\Models\Sala;
use App\Models\Turma;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Regras do ciclo de vida de uma reserva pontual de espaço: pedir, alterar, confirmar e cancelar.
 *
 * Cada operação que ocupa horário trava a linha da sala dentro da transação, para dois pedidos simultâneos do mesmo
 * horário não passarem juntos pela checagem de conflito.
 */
class ReservaEspacoService
{
    public function __construct(
        protected ReservaEspacoConflitoService $conflitos,
    ) {}

    /**
     * Espaços que exigem aprovação começam como "solicitada"; os demais, e os pedidos de quem pode aprovar, já
     * nascem confirmados.
     */
    public function statusInicial(Sala $sala, ?User $solicitante = null): StatusReservaEspaco
    {
        if (! $sala->exige_aprovacao_reserva) {
            return StatusReservaEspaco::Confirmada;
        }

        return $solicitante?->can('Confirmar:ReservaEspaco')
            ? StatusReservaEspaco::Confirmada
            : StatusReservaEspaco::Solicitada;
    }

    /**
     * @param  array{sala_id: int|string, data: mixed, hora_inicio: string, hora_fim: string, motivo: string, descricao?: ?string, turma_id?: int|string|null}  $dados
     *
     * @throws ReservaEspacoIndisponivelException
     */
    public function solicitar(array $dados, User $solicitante): ReservaEspaco
    {
        return DB::transaction(function () use ($dados, $solicitante): ReservaEspaco {
            $sala = Sala::query()->lockForUpdate()->findOrFail($dados['sala_id']);
            $data = Carbon::parse($dados['data'])->startOfDay();
            $inicio = ReservaEspacoConflitoService::normalizarHora($dados['hora_inicio']);
            $fim = ReservaEspacoConflitoService::normalizarHora($dados['hora_fim']);

            $this->validar($sala, $data, $inicio, $fim, null);

            $status = $this->statusInicial($sala, $solicitante);
            $aprovadaNaHora = $sala->exige_aprovacao_reserva && $status === StatusReservaEspaco::Confirmada;

            return ReservaEspaco::create([
                'sala_id' => $sala->id,
                'data' => $data->toDateString(),
                'hora_inicio' => $inicio,
                'hora_fim' => $fim,
                'solicitante_user_id' => $solicitante->id,
                'motivo' => $dados['motivo'],
                'descricao' => $dados['descricao'] ?? null,
                'turma_id' => $dados['turma_id'] ?? null,
                'status' => $status,
                'decidido_por_user_id' => $aprovadaNaHora ? $solicitante->id : null,
                'decidido_em' => $aprovadaNaHora ? now() : null,
            ]);
        });
    }

    /**
     * Altera data, horário, sala ou finalidade. Mudar o horário de uma reserva já confirmada num espaço que exige
     * aprovação a devolve para "aguardando aprovação", a menos que quem altera possa aprovar.
     *
     * @param  array{sala_id: int|string, data: mixed, hora_inicio: string, hora_fim: string, motivo: string, descricao?: ?string, turma_id?: int|string|null}  $dados
     *
     * @throws ReservaEspacoIndisponivelException
     */
    public function atualizar(ReservaEspaco $reserva, array $dados, User $por): ReservaEspaco
    {
        if ($reserva->estaCancelada()) {
            throw ReservaEspacoIndisponivelException::com('Uma reserva cancelada não pode ser alterada. Faça uma nova reserva.');
        }

        if (! $reserva->aindaNaoOcorreu()) {
            throw ReservaEspacoIndisponivelException::com('Esta reserva já ocorreu e não pode mais ser alterada.');
        }

        return DB::transaction(function () use ($reserva, $dados, $por): ReservaEspaco {
            $sala = Sala::query()->lockForUpdate()->findOrFail($dados['sala_id']);
            $data = Carbon::parse($dados['data'])->startOfDay();
            $inicio = ReservaEspacoConflitoService::normalizarHora($dados['hora_inicio']);
            $fim = ReservaEspacoConflitoService::normalizarHora($dados['hora_fim']);

            $mudouOHorario = (int) $reserva->sala_id !== (int) $sala->id
                || ! $reserva->data->isSameDay($data)
                || ReservaEspacoConflitoService::normalizarHora((string) $reserva->hora_inicio) !== $inicio
                || ReservaEspacoConflitoService::normalizarHora((string) $reserva->hora_fim) !== $fim;

            if ($mudouOHorario) {
                $this->validar($sala, $data, $inicio, $fim, $reserva->id);
            }

            $atributos = [
                'sala_id' => $sala->id,
                'data' => $data->toDateString(),
                'hora_inicio' => $inicio,
                'hora_fim' => $fim,
                'motivo' => $dados['motivo'],
                'descricao' => $dados['descricao'] ?? null,
                'turma_id' => $dados['turma_id'] ?? null,
            ];

            if ($mudouOHorario && $sala->exige_aprovacao_reserva && ! $por->can('Confirmar:ReservaEspaco')) {
                $atributos['status'] = StatusReservaEspaco::Solicitada;
                $atributos['decidido_por_user_id'] = null;
                $atributos['decidido_em'] = null;
                $atributos['justificativa_decisao'] = null;
            }

            $reserva->update($atributos);

            return $reserva->refresh();
        });
    }

    /**
     * @throws ReservaEspacoIndisponivelException
     */
    public function confirmar(ReservaEspaco $reserva, User $por): ReservaEspaco
    {
        return DB::transaction(function () use ($reserva, $por): ReservaEspaco {
            Sala::query()->lockForUpdate()->findOrFail($reserva->sala_id);
            $reserva = ReservaEspaco::query()->lockForUpdate()->findOrFail($reserva->id);

            if (! $reserva->aguardaAprovacao()) {
                throw ReservaEspacoIndisponivelException::com('Só reservas aguardando aprovação podem ser confirmadas.');
            }

            if (! $reserva->aindaNaoOcorreu()) {
                throw ReservaEspacoIndisponivelException::com('Esta reserva já passou e não pode mais ser confirmada.');
            }

            // A grade pode ter ganhado uma aula neste horário depois do pedido: confere de novo antes de confirmar.
            $mensagens = $this->conflitos->conflitos(
                (int) $reserva->sala_id,
                $reserva->data,
                (string) $reserva->hora_inicio,
                (string) $reserva->hora_fim,
                $reserva->id,
            );

            if ($mensagens !== []) {
                throw new ReservaEspacoIndisponivelException($mensagens);
            }

            $reserva->update([
                'status' => StatusReservaEspaco::Confirmada,
                'decidido_por_user_id' => $por->id,
                'decidido_em' => now(),
                'justificativa_decisao' => null,
            ]);

            return $reserva->refresh();
        });
    }

    /**
     * Quem cancela a reserva de outra pessoa (por exemplo, a coordenação recusando um pedido) precisa dizer o motivo.
     *
     * @throws ReservaEspacoIndisponivelException
     */
    public function cancelar(ReservaEspaco $reserva, User $por, ?string $justificativa = null): ReservaEspaco
    {
        return DB::transaction(function () use ($reserva, $por, $justificativa): ReservaEspaco {
            $reserva = ReservaEspaco::query()->lockForUpdate()->findOrFail($reserva->id);

            if ($reserva->estaCancelada()) {
                return $reserva;
            }

            if (! $reserva->aindaNaoOcorreu()) {
                throw ReservaEspacoIndisponivelException::com('Esta reserva já ocorreu e não pode mais ser cancelada.');
            }

            $justificativa = trim((string) $justificativa);
            $cancelaDeOutraPessoa = (int) $reserva->solicitante_user_id !== (int) $por->id;

            if ($cancelaDeOutraPessoa && $justificativa === '') {
                throw ReservaEspacoIndisponivelException::com('Informe a justificativa para cancelar a reserva de outra pessoa.');
            }

            $reserva->update([
                'status' => StatusReservaEspaco::Cancelada,
                'decidido_por_user_id' => $por->id,
                'decidido_em' => now(),
                'justificativa_decisao' => $justificativa !== '' ? $justificativa : null,
            ]);

            return $reserva->refresh();
        });
    }

    /**
     * Avisos que não impedem a reserva (por exemplo, turma maior que a capacidade da sala).
     *
     * @return list<string>
     */
    public function avisos(Sala $sala, int|string|null $turmaId): array
    {
        if (! $turmaId || ! $sala->capacidade) {
            return [];
        }

        $turma = Turma::query()->find($turmaId);

        if (! $turma) {
            return [];
        }

        $alunos = $turma->matriculas()->where('situacao', 'ativa')->count();

        if ($alunos > (int) $sala->capacidade) {
            return ["A turma \"{$turma->nome}\" tem {$alunos} alunos ativos e a sala comporta {$sala->capacidade}."];
        }

        return [];
    }

    /**
     * @throws ReservaEspacoIndisponivelException
     */
    private function validar(Sala $sala, Carbon $data, string $inicio, string $fim, ?int $ignorarReservaId): void
    {
        if (! $sala->ativa) {
            throw ReservaEspacoIndisponivelException::com('Esta sala está inativa e não aceita novas reservas.');
        }

        if ($fim <= $inicio) {
            throw ReservaEspacoIndisponivelException::com('O horário final precisa ser depois do horário inicial.');
        }

        if ($data->copy()->setTimeFromTimeString($fim)->isPast()) {
            throw ReservaEspacoIndisponivelException::com('Não é possível reservar um horário que já passou.');
        }

        $mensagens = $this->conflitos->conflitos((int) $sala->id, $data, $inicio, $fim, $ignorarReservaId);

        if ($mensagens !== []) {
            throw new ReservaEspacoIndisponivelException($mensagens);
        }
    }
}
