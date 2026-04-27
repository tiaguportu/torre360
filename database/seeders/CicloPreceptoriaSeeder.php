<?php

namespace Database\Seeders;

use App\Models\CicloPreceptoria;
use App\Models\PeriodoLetivo;
use Illuminate\Database\Seeder;

class CicloPreceptoriaSeeder extends Seeder
{
    public function run(): void
    {
        $periodo = PeriodoLetivo::where('nome', 'like', '%2026%')->first() ?: PeriodoLetivo::first();

        $ciclos = [
            [
                'nome' => '1º Trimestre - 2026',
                'data_inicio' => '2026-01-01',
                'data_fim' => '2026-03-31',
                'periodo_letivo_id' => $periodo?->id,
            ],
            [
                'nome' => '2º Trimestre - 2026',
                'data_inicio' => '2026-04-01',
                'data_fim' => '2026-06-30',
                'periodo_letivo_id' => $periodo?->id,
            ],
            [
                'nome' => '3º Trimestre - 2026',
                'data_inicio' => '2026-07-01',
                'data_fim' => '2026-09-30',
                'periodo_letivo_id' => $periodo?->id,
            ],
            [
                'nome' => '4º Trimestre - 2026',
                'data_inicio' => '2026-10-01',
                'data_fim' => '2026-12-31',
                'periodo_letivo_id' => $periodo?->id,
            ],
        ];

        foreach ($ciclos as $ciclo) {
            CicloPreceptoria::updateOrCreate(
                ['nome' => $ciclo['nome']],
                $ciclo
            );
        }
    }
}
