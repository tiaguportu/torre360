<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Contagem de prazo em horas úteis (Onda 83): só andam as horas dentro da janela de `config/atendimento.php`.
 */
final class HorarioComercial
{
    /**
     * Soma `$horas` horas úteis a partir de `$inicio`. Fora do expediente, a contagem começa na abertura seguinte.
     */
    public static function somarHoras(CarbonInterface $inicio, int $horas): CarbonImmutable
    {
        $cursor = CarbonImmutable::instance($inicio);
        $dias = array_map('intval', (array) config('atendimento.horario_comercial.dias', [1, 2, 3, 4, 5]));

        if ($dias === [] || $horas <= 0) {
            return $cursor->addHours(max(0, $horas));
        }

        $restante = $horas * 60;
        $guarda = 0;

        while ($restante > 0 && $guarda++ < 2000) {
            [$abre, $fecha] = self::janelaDoDia($cursor);

            if (! in_array($cursor->dayOfWeekIso, $dias, true) || $cursor >= $fecha) {
                $cursor = self::abertura($cursor->addDay()->startOfDay());

                continue;
            }

            if ($cursor < $abre) {
                $cursor = $abre;
            }

            $disponivel = (int) $cursor->diffInMinutes($fecha, true);
            $usado = min($restante, $disponivel);
            $cursor = $cursor->addMinutes($usado);
            $restante -= $usado;
        }

        return $cursor;
    }

    /**
     * Soma dias úteis (segunda a sexta, por padrão), sem contar o dia de partida.
     */
    public static function somarDiasUteis(CarbonInterface $inicio, int $dias): CarbonImmutable
    {
        $cursor = CarbonImmutable::instance($inicio);
        $uteis = array_map('intval', (array) config('atendimento.horario_comercial.dias', [1, 2, 3, 4, 5]));

        while ($dias > 0) {
            $cursor = $cursor->addDay();

            if ($uteis === [] || in_array($cursor->dayOfWeekIso, $uteis, true)) {
                $dias--;
            }
        }

        return $cursor;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable} abertura e fechamento do dia de `$momento`
     */
    private static function janelaDoDia(CarbonImmutable $momento): array
    {
        return [
            self::comHora($momento, (string) config('atendimento.horario_comercial.inicio', '08:00')),
            self::comHora($momento, (string) config('atendimento.horario_comercial.fim', '18:00')),
        ];
    }

    private static function abertura(CarbonImmutable $dia): CarbonImmutable
    {
        return self::comHora($dia, (string) config('atendimento.horario_comercial.inicio', '08:00'));
    }

    private static function comHora(CarbonImmutable $dia, string $hhmm): CarbonImmutable
    {
        [$hora, $minuto] = array_pad(array_map('intval', explode(':', $hhmm)), 2, 0);

        return $dia->setTime($hora, $minuto);
    }
}
