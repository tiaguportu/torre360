<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Matricula;
use App\Models\PortfolioItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PortfolioItem>
 */
class PortfolioItemFactory extends Factory
{
    protected $model = PortfolioItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'matricula_id' => Matricula::factory(),
            'titulo' => $this->faker->randomElement(['Maquete do sistema solar', 'Registro da roda de leitura', 'Desenho da família', 'Experimento com plantas']),
            'descricao' => 'Produção realizada em sala, com mediação da professora.',
            'data_registro' => now()->toDateString(),
            'anexos' => [],
            'mostra_outros_alunos' => false,
            'visivel_familia' => false,
            'registrado_por_user_id' => null,
        ];
    }

    public function compartilhado(): static
    {
        return $this->state(fn (): array => ['visivel_familia' => true]);
    }
}
