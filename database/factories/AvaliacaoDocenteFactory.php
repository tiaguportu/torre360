<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StatusAvaliacaoDocente;
use App\Models\AvaliacaoDocente;
use App\Models\CicloAvaliacaoDocente;
use App\Models\Funcionario;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AvaliacaoDocente>
 */
class AvaliacaoDocenteFactory extends Factory
{
    protected $model = AvaliacaoDocente::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ciclo_id' => CicloAvaliacaoDocente::factory(),
            'funcionario_id' => Funcionario::factory(),
            'avaliador_user_id' => User::factory(),
            'turma_id' => null,
            'data_observacao' => now()->toDateString(),
            'pontuacao_percentual' => null,
            'parecer' => null,
            'status' => StatusAvaliacaoDocente::Rascunho,
            'finalizada_em' => null,
        ];
    }

    public function finalizada(float $percentual = 80.0): static
    {
        return $this->state(fn (): array => [
            'status' => StatusAvaliacaoDocente::Finalizada,
            'pontuacao_percentual' => $percentual,
            'finalizada_em' => now(),
        ]);
    }
}
