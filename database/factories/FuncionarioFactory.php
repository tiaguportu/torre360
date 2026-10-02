<?php

namespace Database\Factories;

use App\Models\Funcionario;
use App\Models\Pessoa;
use Illuminate\Database\Eloquent\Factories\Factory;

class FuncionarioFactory extends Factory
{
    protected $model = Funcionario::class;

    public function definition(): array
    {
        return [
            'pessoa_id' => Pessoa::factory(),
            'cargo' => $this->faker->randomElement(['Professor', 'Secretário(a)', 'Coordenador(a)', 'Auxiliar Administrativo']),
            'data_admissao' => $this->faker->dateTimeBetween('-5 years', '-1 month'),
            'regime' => 'clt',
            'carga_horaria_semanal' => $this->faker->randomElement([20, 30, 40]),
        ];
    }
}
