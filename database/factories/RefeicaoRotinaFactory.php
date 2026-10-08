<?php

namespace Database\Factories;

use App\Enums\QuantidadeRefeicao;
use App\Models\RefeicaoRotina;
use App\Models\RegistroRotinaDiaria;
use Illuminate\Database\Eloquent\Factories\Factory;

class RefeicaoRotinaFactory extends Factory
{
    protected $model = RefeicaoRotina::class;

    public function definition(): array
    {
        return [
            'registro_rotina_diaria_id' => RegistroRotinaDiaria::factory(),
            'nome' => 'Almoço',
            'quantidade' => QuantidadeRefeicao::ComeuTudo->value,
            'ordem' => 1,
        ];
    }
}
