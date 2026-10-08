<?php

namespace Database\Factories;

use App\Enums\HumorCrianca;
use App\Models\Matricula;
use App\Models\RegistroRotinaDiaria;
use App\Models\Turma;
use Illuminate\Database\Eloquent\Factories\Factory;

class RegistroRotinaDiariaFactory extends Factory
{
    protected $model = RegistroRotinaDiaria::class;

    public function definition(): array
    {
        return [
            'matricula_id' => Matricula::factory(),
            'turma_id' => Turma::factory(),
            'data' => now()->toDateString(),
            'humor' => HumorCrianca::Feliz->value,
        ];
    }
}
