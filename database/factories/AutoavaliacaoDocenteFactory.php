<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StatusAutoavaliacaoDocente;
use App\Models\AutoavaliacaoDocente;
use App\Models\CicloAvaliacaoDocente;
use App\Models\Funcionario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutoavaliacaoDocente>
 */
class AutoavaliacaoDocenteFactory extends Factory
{
    protected $model = AutoavaliacaoDocente::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ciclo_id' => CicloAvaliacaoDocente::factory(),
            'funcionario_id' => Funcionario::factory(),
            'status' => StatusAutoavaliacaoDocente::Rascunho,
            'comentario' => null,
            'pontuacao_percentual' => null,
            'enviada_em' => null,
        ];
    }

    public function enviada(float $percentual = 70.0): static
    {
        return $this->state(fn (): array => [
            'status' => StatusAutoavaliacaoDocente::Enviada,
            'pontuacao_percentual' => $percentual,
            'enviada_em' => now(),
        ]);
    }
}
