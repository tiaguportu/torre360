<?php

namespace Database\Factories;

use App\Models\CampanhaMarketing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampanhaMarketing>
 */
class CampanhaMarketingFactory extends Factory
{
    protected $model = CampanhaMarketing::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => 'Campanha '.$this->faker->unique()->words(2, true),
            'canal' => $this->faker->randomElement(array_keys(CampanhaMarketing::CANAIS)),
            'codigo_utm' => $this->faker->unique()->slug(2),
            'data_inicio' => now()->subDays(10)->toDateString(),
            'data_fim' => null,
            'custo' => $this->faker->randomFloat(2, 100, 5000),
            'ativa' => true,
        ];
    }

    public function inativa(): static
    {
        return $this->state(fn (array $attributes) => ['ativa' => false]);
    }
}
