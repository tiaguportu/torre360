<?php

namespace Database\Factories;

use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Turma;
use Illuminate\Database\Eloquent\Factories\Factory;

class MatriculaFactory extends Factory
{
    protected $model = Matricula::class;

    /**
     * Período letivo e série da matrícula são os da turma: para escolher o período, crie a turma
     * nele (`'turma_id' => Turma::factory()->create(['periodo_letivo_id' => $periodo->id])`).
     */
    public function definition(): array
    {
        return [
            'pessoa_id' => Pessoa::factory(),
            'turma_id' => Turma::factory(),
            'situacao' => 'ativa',
        ];
    }
}
