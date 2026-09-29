<?php

namespace Database\Factories;

use App\Models\Sala;
use App\Models\Unidade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sala>
 */
class SalaFactory extends Factory
{
    protected $model = Sala::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unidade_id' => fn () => Unidade::create(['nome' => 'Unidade Teste '.$this->faker->unique()->numberBetween(1, 100000)])->id,
            'nome' => 'Sala '.$this->faker->unique()->numberBetween(1, 100),
            'capacidade' => $this->faker->numberBetween(20, 40),
            'tipo' => 'Sala de aula',
            'ativa' => true,
        ];
    }

    public function inativa(): static
    {
        return $this->state(fn (array $attributes) => ['ativa' => false]);
    }
}
