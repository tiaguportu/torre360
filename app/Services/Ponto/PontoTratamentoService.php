<?php

declare(strict_types=1);

namespace App\Services\Ponto;

use App\Enums\StatusTratamentoPonto;
use App\Enums\TipoTratamentoPonto;
use App\Exceptions\PontoException;
use App\Models\Funcionario;
use App\Models\PontoRegistro;
use App\Models\PontoTratamento;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Ajustes sobre a jornada. O trabalhador (ou o RH em nome dele) pede; outra pessoa aprova. O RH também pode registrar
 * um ajuste já aprovado. O registro original da marcação NUNCA é alterado: o ajuste vive ao lado e só conta na apuração
 * depois de aprovado, e a aprovação reapura o dia no banco de horas.
 */
class PontoTratamentoService
{
    public function __construct(
        private readonly BancoHorasService $bancoHoras,
    ) {}

    /**
     * Pedido que fica aguardando aprovação.
     *
     * @throws PontoException
     */
    public function solicitar(Funcionario $funcionario, TipoTratamentoPonto $tipo, CarbonInterface $data, string $motivo, User $por, ?CarbonInterface $momento = null, ?int $registroId = null, ?int $minutosAbono = null): PontoTratamento
    {
        return $this->criar($funcionario, $tipo, $data, $motivo, $por, $momento, $registroId, $minutosAbono, StatusTratamentoPonto::Pendente);
    }

    /**
     * Ajuste lançado pelo RH, que nasce aprovado (a decisão e o registro são da mesma pessoa e ficam identificados).
     *
     * @throws PontoException
     */
    public function registrarAprovado(Funcionario $funcionario, TipoTratamentoPonto $tipo, CarbonInterface $data, string $motivo, User $por, ?CarbonInterface $momento = null, ?int $registroId = null, ?int $minutosAbono = null): PontoTratamento
    {
        $tratamento = $this->criar($funcionario, $tipo, $data, $motivo, $por, $momento, $registroId, $minutosAbono, StatusTratamentoPonto::Aprovado);
        $this->reapurar($tratamento);

        return $tratamento;
    }

    /**
     * @throws PontoException
     */
    public function aprovar(PontoTratamento $tratamento, User $por): PontoTratamento
    {
        $this->exigirPendente($tratamento);

        if ($tratamento->solicitado_por_user_id === $por->id) {
            throw new PontoException('Quem pediu o ajuste não pode aprová-lo. Peça a outra pessoa do RH.');
        }

        $tratamento->update([
            'status' => StatusTratamentoPonto::Aprovado,
            'decidido_por_user_id' => $por->id,
            'decidido_em' => now(),
        ]);

        $this->reapurar($tratamento);

        return $tratamento->refresh();
    }

    /**
     * @throws PontoException
     */
    public function recusar(PontoTratamento $tratamento, User $por): PontoTratamento
    {
        $this->exigirPendente($tratamento);

        $tratamento->update([
            'status' => StatusTratamentoPonto::Recusado,
            'decidido_por_user_id' => $por->id,
            'decidido_em' => now(),
        ]);

        return $tratamento->refresh();
    }

    private function criar(Funcionario $funcionario, TipoTratamentoPonto $tipo, CarbonInterface $data, string $motivo, User $por, ?CarbonInterface $momento, ?int $registroId, ?int $minutosAbono, StatusTratamentoPonto $status): PontoTratamento
    {
        if (! $funcionario->unidade_id) {
            throw new PontoException('O funcionário não tem unidade de lotação.');
        }

        if (mb_strlen(trim($motivo)) < 10) {
            throw new PontoException('Explique o motivo do ajuste (pelo menos 10 caracteres).');
        }

        if ($data->copy()->startOfDay()->gt(today())) {
            throw new PontoException('Não é possível ajustar um dia que ainda não aconteceu.');
        }

        match ($tipo) {
            TipoTratamentoPonto::Inclusao => $this->validarInclusao($data, $momento),
            TipoTratamentoPonto::Desconsiderar => $this->validarDesconsiderar($funcionario, $data, $registroId),
            TipoTratamentoPonto::Abono => $this->validarAbono($minutosAbono),
        };

        return DB::transaction(fn (): PontoTratamento => PontoTratamento::create([
            'unidade_id' => $funcionario->unidade_id,
            'funcionario_id' => $funcionario->id,
            'data' => $data->toDateString(),
            'tipo' => $tipo,
            'momento' => $tipo === TipoTratamentoPonto::Inclusao ? $momento : null,
            'registro_id' => $tipo === TipoTratamentoPonto::Desconsiderar ? $registroId : null,
            'minutos_abono' => $tipo === TipoTratamentoPonto::Abono ? $minutosAbono : null,
            'motivo' => trim($motivo),
            'status' => $status,
            'solicitado_por_user_id' => $por->id,
            'decidido_por_user_id' => $status === StatusTratamentoPonto::Aprovado ? $por->id : null,
            'decidido_em' => $status === StatusTratamentoPonto::Aprovado ? now() : null,
        ]));
    }

    private function validarInclusao(CarbonInterface $data, ?CarbonInterface $momento): void
    {
        if ($momento === null) {
            throw new PontoException('Informe o horário da marcação esquecida.');
        }

        if ($momento->toDateString() !== $data->toDateString()) {
            throw new PontoException('O horário da marcação precisa ser do próprio dia ajustado.');
        }

        if ($momento->gt(now())) {
            throw new PontoException('O horário da marcação não pode estar no futuro.');
        }
    }

    private function validarDesconsiderar(Funcionario $funcionario, CarbonInterface $data, ?int $registroId): void
    {
        $registro = $registroId ? PontoRegistro::query()->find($registroId) : null;

        if (! $registro || $registro->funcionario_id !== $funcionario->id) {
            throw new PontoException('Escolha uma marcação do próprio funcionário para desconsiderar.');
        }

        if ($registro->momento->toDateString() !== $data->toDateString()) {
            throw new PontoException('A marcação escolhida não é do dia ajustado.');
        }

        $jaTratada = PontoTratamento::query()
            ->where('registro_id', $registro->id)
            ->whereIn('status', [StatusTratamentoPonto::Pendente->value, StatusTratamentoPonto::Aprovado->value])
            ->exists();

        if ($jaTratada) {
            throw new PontoException('Esta marcação já tem um pedido de desconsideração em andamento ou aprovado.');
        }
    }

    private function validarAbono(?int $minutos): void
    {
        if ($minutos === null || $minutos < 1 || $minutos > 1440) {
            throw new PontoException('Informe os minutos a abonar (de 1 a 1440).');
        }
    }

    private function exigirPendente(PontoTratamento $tratamento): void
    {
        if ($tratamento->status !== StatusTratamentoPonto::Pendente) {
            throw new PontoException('Este ajuste já foi decidido.');
        }
    }

    /**
     * Depois de aprovado, o dia é apurado de novo no banco de horas (só dias já encerrados).
     */
    private function reapurar(PontoTratamento $tratamento): void
    {
        if ($tratamento->data->copy()->startOfDay()->gte(today())) {
            return;
        }

        $tratamento->loadMissing('funcionario');
        $this->bancoHoras->lancarApuracao($tratamento->funcionario, $tratamento->data);
    }
}
