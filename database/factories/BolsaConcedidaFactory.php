<?php

namespace Database\Factories;

use App\Models\BolsaConcedida;
use App\Models\Matricula;
use App\Models\TipoBolsa;
use Illuminate\Database\Eloquent\Factories\Factory;

class BolsaConcedidaFactory extends Factory
{
    protected $model = BolsaConcedida::class;

    public function definition(): array
    {
        return [
            'matricula_id' => Matricula::factory(),
            'tipo_bolsa_id' => TipoBolsa::factory(),
            'percentual' => $this->faker->numberBetween(10, 50),
            'data_inicio' => now()->subMonth(),
            'status' => 'aprovada',
        ];
    }
}
