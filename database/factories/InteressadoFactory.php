<?php

namespace Database\Factories;

use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Interessado>
 */
class InteressadoFactory extends Factory
{
    protected $model = Interessado::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pessoa_id' => Pessoa::factory(),
            'usuario_id' => User::factory(),
            'origem_interessado_id' => OrigemInteressado::factory(),
            'status_interessado_id' => StatusInteressado::factory(),
            'data_proximo_contato' => $this->faker->optional()->dateTimeBetween('now', '+30 days'),
            'observacoes' => $this->faker->optional()->sentence(),
            'valor_estimado' => $this->faker->optional()->randomFloat(2, 500, 5000),
            'temperatura' => $this->faker->optional()->randomElement(['quente', 'morno', 'frio']),
        ];
    }

    /**
     * Lead novo sem contato.
     */
    public function novo(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_interessado_id' => StatusInteressado::factory()->state(['nome' => 'Novo', 'is_final' => false, 'is_ganho' => false]),
            'data_proximo_contato' => now()->addDays(2),
            'temperatura' => null,
        ]);
    }

    /**
     * Lead em atendimento ativo.
     */
    public function emAtendimento(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_interessado_id' => StatusInteressado::factory()->state(['nome' => 'Em Atendimento', 'is_final' => false, 'is_ganho' => false]),
            'data_proximo_contato' => now()->addDays(3),
            'temperatura' => 'morno',
            'data_primeiro_contato' => now()->subDays(5),
        ]);
    }

    /**
     * Lead perdido.
     */
    public function perdido(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_interessado_id' => StatusInteressado::factory()->state(['nome' => 'Perdido', 'is_final' => true, 'is_ganho' => false]),
            'motivo_perda' => $this->faker->randomElement(['Preço', 'Concorrência', 'Distância', 'Sem retorno']),
            'temperatura' => 'frio',
        ]);
    }

    /**
     * Lead convertido em matrícula.
     */
    public function matriculado(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_interessado_id' => StatusInteressado::factory()->state(['nome' => 'Matriculado', 'is_final' => true, 'is_ganho' => true]),
            'data_conversao' => now(),
            'temperatura' => 'quente',
        ]);
    }

    /**
     * Lead com contato atrasado (precisa de follow-up).
     */
    public function contatoAtrasado(): static
    {
        return $this->state(fn (array $attributes) => [
            'data_proximo_contato' => now()->subDays(3),
        ]);
    }
}
