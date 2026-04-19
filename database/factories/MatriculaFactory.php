<?php

namespace Database\Factories;

use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Turma;
use Illuminate\Database\Eloquent\Factories\Factory;

class MatriculaFactory extends Factory
{
    protected $model = Matricula::class;

    public function definition(): array
    {
        return [
            'codigo' => $this->faker->unique()->bothify('MAT-####'),
            'pessoa_id' => Pessoa::factory(),
            'turma_id' => Turma::factory(),
            'periodo_letivo_id' => PeriodoLetivo::factory(),
            'situacao' => 'ativa',
        ];
    }
}
