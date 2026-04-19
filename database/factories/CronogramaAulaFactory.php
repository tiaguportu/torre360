<?php

namespace Database\Factories;

use App\Models\CronogramaAula;
use App\Models\Disciplina;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Turma;
use Illuminate\Database\Eloquent\Factories\Factory;

class CronogramaAulaFactory extends Factory
{
    protected $model = CronogramaAula::class;

    public function definition(): array
    {
        return [
            'turma_id' => Turma::factory(),
            'disciplina_id' => Disciplina::factory(),
            'pessoa_id' => Pessoa::factory(), // Professor
            'periodo_letivo_id' => PeriodoLetivo::factory(),
            'data' => $this->faker->date(),
            'conteudo' => $this->faker->sentence(),
        ];
    }
}
