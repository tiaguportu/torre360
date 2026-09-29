<?php

namespace Database\Factories;

use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\MatrizCurricular;
use App\Models\Serie;
use App\Models\Unidade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MatrizCurricular>
 */
class MatrizCurricularFactory extends Factory
{
    protected $model = MatrizCurricular::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'serie_id' => fn () => Serie::create([
                'nome' => 'Série Teste '.$this->faker->unique()->numberBetween(1, 100000),
                'curso_id' => Curso::create([
                    'unidade_id' => Unidade::create(['nome' => 'Unidade Teste '.$this->faker->unique()->numberBetween(1, 100000)])->id,
                    'nome_externo' => 'Curso Teste',
                    'nome_interno' => 'Curso Teste',
                ])->id,
                'sistema_avaliacao' => 'Nota',
            ])->id,
            'disciplina_id' => Disciplina::factory(),
            'carga_horaria_semanal' => $this->faker->numberBetween(1, 5),
            'obrigatoria' => true,
            'ordem' => $this->faker->numberBetween(1, 20),
        ];
    }

    public function optativa(): static
    {
        return $this->state(fn (array $attributes) => ['obrigatoria' => false]);
    }
}
