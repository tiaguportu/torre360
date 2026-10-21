<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StatusExecucaoManutencao;
use App\Models\ExecucaoManutencao;
use App\Models\PlanoManutencao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExecucaoManutencao>
 */
class ExecucaoManutencaoFactory extends Factory
{
    protected $model = ExecucaoManutencao::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plano_manutencao_id' => PlanoManutencao::factory(),
            'ordem_servico_id' => null,
            'data_prevista' => now()->addDays(10)->toDateString(),
            'status' => StatusExecucaoManutencao::Prevista,
            'concluida_em' => null,
            'checklist_resultado' => null,
            'observacoes' => null,
            'registrado_por_user_id' => null,
        ];
    }
}
