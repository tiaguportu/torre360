<?php

namespace Database\Factories;

use App\Enums\StatusTransferencia;
use App\Enums\TipoTransferencia;
use App\Models\Matricula;
use App\Models\TransferenciaEscolar;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransferenciaEscolarFactory extends Factory
{
    protected $model = TransferenciaEscolar::class;

    public function definition(): array
    {
        return [
            'matricula_id' => Matricula::factory(),
            'tipo' => TipoTransferencia::Saida->value,
            'escola_externa_nome' => $this->faker->company().' — Escola',
            'escola_externa_cidade' => $this->faker->city(),
            'escola_externa_uf' => strtoupper($this->faker->lexify('??')),
            'data' => now()->toDateString(),
            'status' => StatusTransferencia::EmAndamento->value,
        ];
    }
}
