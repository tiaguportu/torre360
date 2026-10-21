<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Funcionario;
use App\Models\PontoJornada;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PontoJornada>
 */
class PontoJornadaFactory extends Factory
{
    protected $model = PontoJornada::class;

    /**
     * Padrão: segunda a sexta, 8 horas por dia.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'funcionario_id' => Funcionario::factory(),
            'vigencia_inicio' => '2026-01-01',
            'vigencia_fim' => null,
            'minutos_seg' => 480,
            'minutos_ter' => 480,
            'minutos_qua' => 480,
            'minutos_qui' => 480,
            'minutos_sex' => 480,
            'minutos_sab' => 0,
            'minutos_dom' => 0,
        ];
    }
}
