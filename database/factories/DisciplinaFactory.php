<?php

namespace Database\Factories;

use App\Models\Disciplina;
use Illuminate\Database\Eloquent\Factories\Factory;

class DisciplinaFactory extends Factory
{
    protected $model = Disciplina::class;

    public function definition(): array
    {
        return [
            'nome' => 'Disciplina Teste '.$this->faker->unique()->word,
            'slug' => $this->faker->unique()->slug,
        ];
    }
}
