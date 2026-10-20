<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CriterioAvaliacaoDocente;
use App\Models\InstrumentoAvaliacaoDocente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CriterioAvaliacaoDocente>
 */
class CriterioAvaliacaoDocenteFactory extends Factory
{
    protected $model = CriterioAvaliacaoDocente::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'instrumento_id' => InstrumentoAvaliacaoDocente::factory(),
            'nome' => $this->faker->randomElement(['Planejamento da aula', 'Uso de metodologias ativas', 'Gestão do tempo', 'Mediação e clima de sala']),
            'descricao' => null,
            'peso' => 1,
            'ordem' => 1,
        ];
    }
}
