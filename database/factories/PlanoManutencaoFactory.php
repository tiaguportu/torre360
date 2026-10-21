<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PlanoManutencao;
use App\Models\Sala;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanoManutencao>
 */
class PlanoManutencaoFactory extends Factory
{
    protected $model = PlanoManutencao::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'titulo' => $this->faker->randomElement(['Revisão dos extintores', 'Limpeza do ar-condicionado', 'Inspeção elétrica', 'Manutenção do portão']),
            'descricao' => null,
            'bem_patrimonial_id' => null,
            'sala_id' => Sala::factory(),
            'periodicidade_dias' => 90,
            'proxima_execucao' => now()->addDays(60)->toDateString(),
            'ultima_execucao' => null,
            'antecedencia_dias' => 7,
            'prioridade' => 'Média',
            'categoria_os_id' => null,
            'checklist' => ['Verificar o estado geral', 'Registrar anomalias'],
            'ativo' => true,
            'criado_por_user_id' => null,
        ];
    }

    public function vencendoEm(int $dias): static
    {
        return $this->state(fn (): array => ['proxima_execucao' => now()->addDays($dias)->toDateString()]);
    }

    public function inativo(): static
    {
        return $this->state(fn (): array => ['ativo' => false]);
    }
}
