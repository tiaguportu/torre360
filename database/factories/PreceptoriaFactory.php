<?php

namespace Database\Factories;

use App\Models\CicloPreceptoria;
use App\Models\Pessoa;
use App\Models\Preceptoria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Preceptoria>
 */
class PreceptoriaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'professor_id' => Pessoa::factory(),
            'data' => now()->toDateString(),
            'hora_inicio' => '14:00:00',
            'hora_fim' => '14:30:00',
        ];
    }

    /**
     * Num ciclo em andamento do período letivo padrão, o mesmo das turmas da fábrica: serve a quem
     * agenda um aluno, que só entra em ciclo do período letivo da matrícula.
     */
    public function comCicloValido(): static
    {
        return $this->state(fn () => [
            'ciclo_preceptoria_id' => CicloPreceptoria::firstOrCreate(
                ['nome' => 'Ciclo Válido de Teste', 'periodo_letivo_id' => PeriodoLetivoFactory::idPadrao()],
                ['data_inicio' => now()->subMonth()->toDateString(), 'data_fim' => now()->addMonths(2)->toDateString()],
            )->id,
        ]);
    }
}
