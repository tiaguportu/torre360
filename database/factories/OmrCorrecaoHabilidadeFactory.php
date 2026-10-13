<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Habilidade;
use App\Models\OmrCorrecao;
use App\Models\OmrCorrecaoHabilidade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OmrCorrecaoHabilidade>
 */
class OmrCorrecaoHabilidadeFactory extends Factory
{
    protected $model = OmrCorrecaoHabilidade::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'omr_correcao_id' => OmrCorrecao::factory(),
            'habilidade_id' => Habilidade::factory(),
            'acertos' => 4,
            'total' => 5,
            'percentual' => 80.00,
        ];
    }
}
