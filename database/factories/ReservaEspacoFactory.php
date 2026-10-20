<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StatusReservaEspaco;
use App\Models\ReservaEspaco;
use App\Models\Sala;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReservaEspaco>
 */
class ReservaEspacoFactory extends Factory
{
    protected $model = ReservaEspaco::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sala_id' => Sala::factory(),
            'data' => now()->addDays(3)->toDateString(),
            'hora_inicio' => '14:00',
            'hora_fim' => '15:00',
            'solicitante_user_id' => User::factory(),
            'motivo' => $this->faker->randomElement(['Ensaio de formatura', 'Palestra para os pais', 'Aula extra de Educação Física']),
            'descricao' => null,
            'turma_id' => null,
            'status' => StatusReservaEspaco::Confirmada,
        ];
    }

    public function solicitada(): static
    {
        return $this->state(fn (): array => ['status' => StatusReservaEspaco::Solicitada]);
    }

    public function cancelada(): static
    {
        return $this->state(fn (): array => ['status' => StatusReservaEspaco::Cancelada]);
    }
}
