<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StatusPlanoDesenvolvimento;
use App\Models\AvaliacaoDocente;
use App\Models\PlanoDesenvolvimentoDocente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanoDesenvolvimentoDocente>
 */
class PlanoDesenvolvimentoDocenteFactory extends Factory
{
    protected $model = PlanoDesenvolvimentoDocente::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'avaliacao_docente_id' => AvaliacaoDocente::factory()->finalizada(),
            'funcionario_id' => fn (array $atributos): int => AvaliacaoDocente::findOrFail($atributos['avaliacao_docente_id'])->funcionario_id,
            'status' => StatusPlanoDesenvolvimento::Rascunho,
            'objetivo' => null,
            'data_revisao' => null,
            'criado_por_user_id' => null,
        ];
    }

    public function ativo(): static
    {
        return $this->state(fn (): array => [
            'status' => StatusPlanoDesenvolvimento::Ativo,
            'ativado_em' => now(),
        ]);
    }
}
