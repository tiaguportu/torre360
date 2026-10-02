<?php

namespace Database\Factories;

use App\Models\BemPatrimonial;
use Illuminate\Database\Eloquent\Factories\Factory;

class BemPatrimonialFactory extends Factory
{
    protected $model = BemPatrimonial::class;

    public function definition(): array
    {
        return [
            'descricao' => $this->faker->randomElement(['Notebook Dell', 'Carteira Escolar', 'Projetor', 'Mesa de Professor', 'Microscópio']),
            'numero_patrimonio' => $this->faker->unique()->numerify('PAT-#####'),
            'categoria' => $this->faker->randomElement(['Informática', 'Mobiliário', 'Material Pedagógico', 'Laboratório']),
            'status' => 'em_uso',
        ];
    }
}
