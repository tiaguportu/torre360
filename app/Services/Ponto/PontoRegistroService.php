<?php

declare(strict_types=1);

namespace App\Services\Ponto;

use App\Enums\OrigemRegistroPonto;
use App\Exceptions\PontoException;
use App\Models\Funcionario;
use App\Models\PontoConfiguracao;
use App\Models\PontoRegistro;
use Illuminate\Support\Facades\DB;

/**
 * Grava a marcação de ponto. O horário é SEMPRE o do servidor (nunca o informado pelo aparelho) e cada registro entra na
 * cadeia da unidade com o próximo NSR e o hash do anterior, sob lock da configuração para que dois toques simultâneos
 * não repitam número.
 *
 * Marcar o ponto nunca é bloqueado por falha de conformidade (hora legal fora do limite, assinatura ausente): o
 * trabalhador não pode ser impedido de registrar (Portaria MTP 671/2021, art. 74). A falha vira alerta para a gestão.
 */
class PontoRegistroService
{
    public function __construct(
        private readonly HoraLegalProvider $horaLegal,
    ) {}

    /**
     * @throws PontoException
     */
    public function registrar(Funcionario $funcionario, OrigemRegistroPonto $origem = OrigemRegistroPonto::Web, ?string $ip = null): PontoRegistro
    {
        if (! $funcionario->isAtivo()) {
            throw new PontoException('Funcionário desligado não registra ponto.');
        }

        if (! $funcionario->unidade_id) {
            throw new PontoException('O funcionário não tem unidade de lotação. Procure o RH.');
        }

        // A consulta de rede fica fora da transação para não segurar o lock enquanto espera o servidor NTP.
        $desvio = $this->horaLegal->desvioSegundos();
        $limite = (int) config('ponto.limite_desvio_segundos', 30);

        return DB::transaction(function () use ($funcionario, $origem, $ip, $desvio, $limite): PontoRegistro {
            $config = PontoConfiguracao::query()->where('unidade_id', $funcionario->unidade_id)->lockForUpdate()->first();

            if (! $config || ! $config->ativo) {
                throw new PontoException('O ponto desta unidade ainda não foi configurado ou está desativado. Procure o RH.');
            }

            $agora = now()->startOfSecond();

            $anterior = PontoRegistro::query()
                ->where('funcionario_id', $funcionario->id)
                ->orderByDesc('momento')
                ->orderByDesc('id')
                ->first();

            // Anti-duplo-clique: o segundo toque devolve a marcação que acabou de ser gravada.
            if ($anterior && abs($agora->timestamp - $anterior->momento->timestamp) < (int) config('ponto.duplo_clique_segundos', 10)) {
                return $anterior;
            }

            $nsr = $config->ultimo_nsr + 1;
            $momento = $agora->format('Y-m-d H:i:s');
            $fuso = $agora->format('P');
            $hash = PontoRegistro::calcularHash((int) $funcionario->unidade_id, $nsr, $funcionario->id, $momento, $fuso, $origem->value, $config->hash_ultimo_registro);

            $registro = PontoRegistro::create([
                'unidade_id' => $funcionario->unidade_id,
                'funcionario_id' => $funcionario->id,
                'nsr' => $nsr,
                'momento' => $momento,
                'fuso' => $fuso,
                'origem' => $origem->value,
                'ip' => $ip,
                'hora_legal_ok' => $desvio !== null && abs($desvio) <= $limite,
                'desvio_segundos' => $desvio,
                'hash_anterior' => $config->hash_ultimo_registro,
                'hash' => $hash,
            ]);

            $config->forceFill(['ultimo_nsr' => $nsr, 'hash_ultimo_registro' => $hash])->save();

            return $registro;
        });
    }
}
