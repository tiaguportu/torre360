<?php

namespace Database\Factories;

use App\Models\Livro;
use Illuminate\Database\Eloquent\Factories\Factory;

class LivroFactory extends Factory
{
    protected $model = Livro::class;

    public function definition(): array
    {
        return [
            'titulo' => $this->faker->sentence(3),
            'autor' => $this->faker->name(),
            'isbn' => $this->faker->numerify('978-##-####-###-#'),
            'categoria' => $this->faker->randomElement(['Infantil', 'Didático', 'Literatura', 'Paradidático']),
            'quantidade_total' => 3,
            'quantidade_disponivel' => 3,
        ];
    }
}
