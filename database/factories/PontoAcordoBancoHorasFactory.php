<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TipoAcordoBancoHoras;
use App\Models\Funcionario;
use App\Models\PontoAcordoBancoHoras;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PontoAcordoBancoHoras>
 */
class PontoAcordoBancoHorasFactory extends Factory
{
    protected $model = PontoAcordoBancoHoras::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'funcionario_id' => Funcionario::factory(),
            'tipo' => TipoAcordoBancoHoras::IndividualEscrito,
            'vigencia_inicio' => '2026-01-01',
            'vigencia_fim' => null,
            'prazo_compensacao_meses' => 6,
            'documento_referencia' => 'Termo de acordo individual nº 001/2026',
        ];
    }
}
