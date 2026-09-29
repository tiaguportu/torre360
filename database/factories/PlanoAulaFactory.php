<?php

namespace Database\Factories;

use App\Models\Disciplina;
use App\Models\PlanoAula;
use App\Models\Turma;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanoAula>
 */
class PlanoAulaFactory extends Factory
{
    protected $model = PlanoAula::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'turma_id' => Turma::factory(),
            'disciplina_id' => Disciplina::factory(),
            'professor_id' => null,
            'data_prevista' => now()->addDays(3)->toDateString(),
            'objetivos' => $this->faker->sentence(),
            'metodologia' => $this->faker->sentence(),
        ];
    }

    public function executado(): static
    {
        return $this->state(fn (array $attributes) => ['executado_em' => now()]);
    }
}
