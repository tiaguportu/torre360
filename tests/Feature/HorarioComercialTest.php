<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\HorarioComercial;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Prazo em horas úteis (Onda 83): só andam as horas dentro da janela de 08:00 às 18:00, de segunda a sexta.
 * Quarta-feira é 21/10/2026.
 */
class HorarioComercialTest extends TestCase
{
    public function test_dentro_do_expediente_soma_direto(): void
    {
        $fim = HorarioComercial::somarHoras(Carbon::parse('2026-10-21 09:00'), 2);

        $this->assertSame('2026-10-21 11:00', $fim->format('Y-m-d H:i'));
    }

    public function test_passa_do_fim_do_expediente_continua_na_abertura_do_dia_util_seguinte(): void
    {
        // 1 h na quarta (17h às 18h) e 2 h na quinta (8h às 10h).
        $fim = HorarioComercial::somarHoras(Carbon::parse('2026-10-21 17:00'), 3);

        $this->assertSame('2026-10-22 10:00', $fim->format('Y-m-d H:i'));
    }

    public function test_sexta_a_tarde_continua_na_segunda(): void
    {
        $fim = HorarioComercial::somarHoras(Carbon::parse('2026-10-23 16:00'), 4);

        $this->assertSame('2026-10-26 10:00', $fim->format('Y-m-d H:i'));
    }

    public function test_pedido_no_fim_de_semana_comeca_a_contar_na_segunda(): void
    {
        $fim = HorarioComercial::somarHoras(Carbon::parse('2026-10-24 10:00'), 1);

        $this->assertSame('2026-10-26 09:00', $fim->format('Y-m-d H:i'));
    }

    public function test_pedido_antes_da_abertura_comeca_na_abertura(): void
    {
        $fim = HorarioComercial::somarHoras(Carbon::parse('2026-10-21 06:30'), 1);

        $this->assertSame('2026-10-21 09:00', $fim->format('Y-m-d H:i'));
    }

    public function test_prazo_de_um_dia_util_a_partir_da_sexta_cai_na_segunda(): void
    {
        $fim = HorarioComercial::somarDiasUteis(Carbon::parse('2026-10-23 15:00'), 1);

        $this->assertSame('2026-10-26', $fim->format('Y-m-d'));
    }

    public function test_72_horas_uteis_a_partir_da_quarta_terminam_na_sexta_da_semana_seguinte(): void
    {
        // 9 h na quarta + 6 dias de 10 h (quinta, sexta, segunda a quinta) = 69 h; as 3 h que faltam caem na sexta, às 11h.
        $fim = HorarioComercial::somarHoras(Carbon::parse('2026-10-21 09:00'), 72);

        $this->assertSame('2026-10-30 11:00', $fim->format('Y-m-d H:i'));
    }
}
