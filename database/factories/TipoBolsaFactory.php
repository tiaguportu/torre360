<?php

namespace Database\Factories;

use App\Models\TipoBolsa;
use Illuminate\Database\Eloquent\Factories\Factory;

class TipoBolsaFactory extends Factory
{
    protected $model = TipoBolsa::class;

    public function definition(): array
    {
        return [
            'nome' => $this->faker->unique()->randomElement(['Bolsa Filantrópica', 'Convênio Empresa', 'Desconto Irmãos', 'Bolsa Mérito']),
            'percentual_maximo' => $this->faker->randomElement([20, 30, 50, 100]),
            'exige_aprovacao' => true,
        ];
    }
}
