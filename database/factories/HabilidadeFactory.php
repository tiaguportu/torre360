<?php

namespace Database\Factories;

use App\Models\Habilidade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Habilidade>
 */
class HabilidadeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => 'EF'.fake()->numerify('##MA##'),
            'nome' => fake()->sentence(3),
            'descricao' => fake()->paragraph(),
            'tipo' => 'fundamental',
        ];
    }
}
