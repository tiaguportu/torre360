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
            'data_inicio' => '2026-01-01',
            'data_fim' => '2026-12-31',
        ];
    }

    /**
     * Id de um período já existente (o mais antigo) ou, se não houver nenhum, de um recém-criado.
     *
     * Turma exige período letivo; testes que criam `Turma::create([...])` sem se importar com o
     * período usam isto para não multiplicar períodos (o wizard pré-seleciona o último criado).
     */
    public static function idPadrao(): int
    {
        return PeriodoLetivo::query()->oldest('id')->value('id')
            ?? PeriodoLetivo::factory()->create()->id;
    }
}
