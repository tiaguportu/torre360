<?php

namespace Database\Factories;

use App\Enums\StatusListaEspera;
use App\Models\ListaEsperaMatricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Turma;
use Illuminate\Database\Eloquent\Factories\Factory;

class ListaEsperaMatriculaFactory extends Factory
{
    protected $model = ListaEsperaMatricula::class;

    public function definition(): array
    {
        return [
            'turma_id' => Turma::factory(),
            'periodo_letivo_id' => PeriodoLetivo::factory(),
            'pessoa_id' => Pessoa::factory(),
            'status' => StatusListaEspera::Aguardando->value,
        ];
    }
}
