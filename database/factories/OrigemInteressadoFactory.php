<?php

namespace Database\Factories;

use App\Models\OrigemInteressado;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrigemInteressado>
 */
class OrigemInteressadoFactory extends Factory
{
    protected $model = OrigemInteressado::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => $this->faker->unique()->randomElement(['Site', 'Indicação', 'Instagram', 'Facebook', 'Presencial', 'Ligação']),
        ];
    }
}
