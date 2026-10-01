<?php

namespace Database\Factories;

use App\Models\ContaPagar;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContaPagarFactory extends Factory
{
    protected $model = ContaPagar::class;

    public function definition(): array
    {
        return [
            'descricao' => 'Conta a Pagar '.$this->faker->unique()->word,
            'valor' => $this->faker->randomFloat(2, 100, 5000),
            'vencimento' => $this->faker->dateTimeBetween('-10 days', '+30 days'),
            'status' => 'pendente',
        ];
    }
}
