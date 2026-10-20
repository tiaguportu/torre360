<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CriterioAvaliacaoDocente;
use App\Models\InstrumentoAvaliacaoDocente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstrumentoAvaliacaoDocente>
 */
class InstrumentoAvaliacaoDocenteFactory extends Factory
{
    protected $model = InstrumentoAvaliacaoDocente::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => 'Observação em sala '.$this->faker->unique()->numberBetween(1, 100000),
            'descricao' => null,
            'escala_maxima' => 5,
            'ativo' => true,
        ];
    }

    /**
     * Cria o instrumento já com critérios (nome, peso). Sem argumentos: planejamento e mediação, ambos peso 1.
     *
     * @param  array<int, array{nome: string, peso?: float|int}>|null  $criterios
     */
    public function comCriterios(?array $criterios = null): static
    {
        $criterios ??= [
            ['nome' => 'Planejamento da aula', 'peso' => 1],
            ['nome' => 'Mediação e clima de sala', 'peso' => 1],
        ];

        return $this->afterCreating(function (InstrumentoAvaliacaoDocente $instrumento) use ($criterios): void {
            foreach (array_values($criterios) as $i => $criterio) {
                CriterioAvaliacaoDocente::create([
                    'instrumento_id' => $instrumento->id,
                    'nome' => $criterio['nome'],
                    'peso' => $criterio['peso'] ?? 1,
                    'ordem' => $i + 1,
                ]);
            }
        });
    }
}
