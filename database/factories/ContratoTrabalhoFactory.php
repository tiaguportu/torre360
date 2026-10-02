<?php

namespace Database\Factories;

use App\Models\ContratoTrabalho;
use App\Models\Funcionario;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContratoTrabalhoFactory extends Factory
{
    protected $model = ContratoTrabalho::class;

    public function definition(): array
    {
        return [
            'funcionario_id' => Funcionario::factory(),
            'vigencia_inicio' => $this->faker->dateTimeBetween('-2 years', '-1 month'),
            'salario' => $this->faker->randomFloat(2, 1800, 12000),
        ];
    }
}
