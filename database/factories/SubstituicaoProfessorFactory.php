<?php

namespace Database\Factories;

use App\Models\Pessoa;
use App\Models\SubstituicaoProfessor;
use App\Models\Turma;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubstituicaoProfessorFactory extends Factory
{
    protected $model = SubstituicaoProfessor::class;

    public function definition(): array
    {
        return [
            'turma_id' => Turma::factory(),
            'professor_titular_id' => Pessoa::factory(),
            'professor_substituto_id' => Pessoa::factory(),
            'data_inicio' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'motivo' => $this->faker->randomElement(['Licença médica', 'Férias', 'Capacitação']),
        ];
    }
}
