<?php

namespace Database\Factories;

use App\Enums\StatusVisitaInteressado;
use App\Models\Interessado;
use App\Models\User;
use App\Models\VisitaInteressado;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VisitaInteressado>
 */
class VisitaInteressadoFactory extends Factory
{
    protected $model = VisitaInteressado::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'interessado_id' => Interessado::factory(),
            'usuario_id' => User::factory(),
            'data_hora' => now()->addDays(2)->setTime(10, 0),
            'status' => StatusVisitaInteressado::Agendada,
        ];
    }

    public function realizada(): static
    {
        return $this->state(fn (array $attributes) => [
            'data_hora' => now()->subDay(),
            'status' => StatusVisitaInteressado::Realizada,
        ]);
    }

    public function daquiAHoras(int $horas): static
    {
        return $this->state(fn (array $attributes) => ['data_hora' => now()->addHours($horas)]);
    }
}
