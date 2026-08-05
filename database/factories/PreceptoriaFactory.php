<?php

namespace Database\Factories;

use App\Models\Pessoa;
use App\Models\Preceptoria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Preceptoria>
 */
class PreceptoriaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'professor_id' => Pessoa::factory(),
            'data' => now()->toDateString(),
            'hora_inicio' => '14:00:00',
            'hora_fim' => '14:30:00',
        ];
    }
}
