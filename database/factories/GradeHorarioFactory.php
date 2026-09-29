<?php

namespace Database\Factories;

use App\Models\Disciplina;
use App\Models\GradeHorario;
use App\Models\Turma;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradeHorario>
 */
class GradeHorarioFactory extends Factory
{
    protected $model = GradeHorario::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'turma_id' => Turma::factory(),
            'disciplina_id' => Disciplina::factory(),
            'professor_id' => null,
            'sala_id' => null,
            'dia_semana' => $this->faker->numberBetween(1, 5),
            'hora_inicio' => '08:00:00',
            'hora_fim' => '08:50:00',
        ];
    }
}
