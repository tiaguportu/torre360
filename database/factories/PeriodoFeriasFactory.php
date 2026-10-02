<?php

namespace Database\Factories;

use App\Models\Funcionario;
use App\Models\PeriodoFerias;
use Illuminate\Database\Eloquent\Factories\Factory;

class PeriodoFeriasFactory extends Factory
{
    protected $model = PeriodoFerias::class;

    public function definition(): array
    {
        $inicio = $this->faker->dateTimeBetween('-2 years', '-1 year');

        return [
            'funcionario_id' => Funcionario::factory(),
            'periodo_aquisitivo_inicio' => $inicio,
            'periodo_aquisitivo_fim' => (clone $inicio)->modify('+1 year'),
            'dias_direito' => 30,
            'dias_gozados' => 0,
            'status' => 'pendente',
        ];
    }
}
