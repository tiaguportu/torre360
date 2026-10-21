<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ModoPonto;
use App\Models\PontoConfiguracao;
use App\Models\Unidade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PontoConfiguracao>
 */
class PontoConfiguracaoFactory extends Factory
{
    protected $model = PontoConfiguracao::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unidade_id' => fn (): int => Unidade::create(['nome' => 'Unidade '.uniqid(), 'situacao_funcionamento' => '1'])->id,
            'modo' => ModoPonto::ControleInterno,
            'ativo' => true,
            'exige_hora_legal' => true,
            'tolerancia_diaria_minutos' => 10,
        ];
    }
}
