<?php

declare(strict_types=1);

namespace App\Services\Ponto;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Consulta servidores NTP.br (NIC.br), sincronizados com a Hora Legal Brasileira disseminada pelo Observatório
 * Nacional. O resultado fica em cache por um minuto para não abrir uma consulta UDP a cada marcação.
 */
class NtpHoraLegalProvider implements HoraLegalProvider
{
    private const CHAVE_CACHE = 'ponto.desvio_hora_legal';

    /** Segundos entre 1900 (época do NTP) e 1970 (época Unix). */
    private const DELTA_EPOCA_NTP = 2208988800;

    public function desvioSegundos(): ?int
    {
        $valor = Cache::remember(self::CHAVE_CACHE, 60, fn (): int|string => $this->consultar() ?? 'falha');

        return $valor === 'falha' ? null : (int) $valor;
    }

    public function verificaHoraLegal(): bool
    {
        return true;
    }

    private function consultar(): ?int
    {
        $timeout = max(1, (int) config('ponto.ntp_timeout_segundos', 2));

        foreach ((array) config('ponto.servidores_ntp', []) as $servidor) {
            try {
                $socket = @stream_socket_client("udp://{$servidor}:123", $erroNumero, $erroTexto, $timeout);

                if (! $socket) {
                    continue;
                }

                stream_set_timeout($socket, $timeout);

                $envio = microtime(true);
                fwrite($socket, chr(0x1B).str_repeat("\0", 47));
                $resposta = (string) fread($socket, 48);
                $recebimento = microtime(true);
                fclose($socket);

                $desvio = self::desvioDaResposta($resposta, $envio, $recebimento);

                if ($desvio !== null) {
                    return $desvio;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * Interpreta o pacote NTP de 48 bytes: o carimbo de transmissão do servidor (bytes 40 a 47) é comparado com o
     * ponto médio entre o envio e o recebimento, o que compensa o atraso de rede.
     */
    public static function desvioDaResposta(string $resposta, float $envio, float $recebimento): ?int
    {
        if (strlen($resposta) < 48) {
            return null;
        }

        $campos = unpack('N12', $resposta);

        if ($campos === false || ($campos[11] ?? 0) === 0) {
            return null;
        }

        $servidor = ($campos[11] - self::DELTA_EPOCA_NTP) + ($campos[12] / 4294967296);

        return (int) round($servidor - (($envio + $recebimento) / 2));
    }
}
