<?php

namespace Database\Factories;

use App\Models\PeriodoLetivo;
use Illuminate\Database\Eloquent\Factories\Factory;

class PeriodoLetivoFactory extends Factory
{
    protected $model = PeriodoLetivo::class;

    public function definition(): array
    {
        return [
            'nome' => '2026',
            'slug' => '2026',
            'ano' => 2026,
            'inicio' => '2026-01-01',
            'fim' => '2026-12-31',
        ];
    }
}
