<?php

namespace Database\Factories;

use App\Models\Disciplina;
use App\Models\MaterialAula;
use App\Models\Turma;
use Illuminate\Database\Eloquent\Factories\Factory;

class MaterialAulaFactory extends Factory
{
    protected $model = MaterialAula::class;

    public function definition(): array
    {
        return [
            'turma_id' => Turma::factory(),
            'disciplina_id' => Disciplina::factory(),
            'titulo' => $this->faker->sentence(4),
            'tipo' => 'link',
            'url' => 'https://example.com/material',
            'data_publicacao' => now(),
            'visivel' => true,
        ];
    }
}
