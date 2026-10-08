<?php

namespace Database\Factories;

use App\Enums\StatusTurma;
use App\Models\Turma;
use Illuminate\Database\Eloquent\Factories\Factory;

class TurmaFactory extends Factory
{
    protected $model = Turma::class;

    public function definition(): array
    {
        return [
            'nome' => 'Turma Teste '.$this->faker->unique()->word,
            'periodo_letivo_id' => fn () => PeriodoLetivoFactory::idPadrao(),
            'status' => StatusTurma::Ativa,
        ];
    }

    public function planejada(): static
    {
        return $this->state(['status' => StatusTurma::Planejada]);
    }

    public function concluida(): static
    {
        return $this->state(['status' => StatusTurma::Concluida]);
    }

    public function cancelada(): static
    {
        return $this->state(['status' => StatusTurma::Cancelada]);
    }
}
