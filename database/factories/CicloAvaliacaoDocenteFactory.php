<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CicloAvaliacaoDocente;
use App\Models\InstrumentoAvaliacaoDocente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CicloAvaliacaoDocente>
 */
class CicloAvaliacaoDocenteFactory extends Factory
{
    protected $model = CicloAvaliacaoDocente::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => 'Ciclo '.$this->faker->unique()->numberBetween(1, 100000),
            'data_inicio' => now()->startOfMonth()->toDateString(),
            'data_fim' => now()->addMonths(5)->endOfMonth()->toDateString(),
            'instrumento_id' => InstrumentoAvaliacaoDocente::factory()->comCriterios(),
            'ativo' => true,
        ];
    }

    public function encerrado(): static
    {
        return $this->state(fn (): array => ['ativo' => false]);
    }
}
