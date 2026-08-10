<?php

namespace Database\Factories;

use App\Models\StatusInteressado;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StatusInteressado>
 */
class StatusInteressadoFactory extends Factory
{
    protected $model = StatusInteressado::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => $this->faker->unique()->word(),
            'cor' => $this->faker->randomElement(['info', 'warning', 'success', 'danger', 'gray']),
            'ordem' => $this->faker->numberBetween(0, 10),
            'is_final' => false,
            'is_ganho' => false,
        ];
    }

    /**
     * Status de conversão (ganho).
     */
    public function ganho(): static
    {
        return $this->state(fn (array $attributes) => [
            'nome' => 'Matriculado',
            'is_final' => true,
            'is_ganho' => true,
            'cor' => 'success',
        ]);
    }

    /**
     * Status de perda.
     */
    public function perdido(): static
    {
        return $this->state(fn (array $attributes) => [
            'nome' => 'Perdido',
            'is_final' => true,
            'is_ganho' => false,
            'cor' => 'danger',
        ]);
    }
}
