<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AvaliacaoGabarito;
use App\Models\AvaliacaoGabaritoQuestao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AvaliacaoGabaritoQuestao>
 */
class AvaliacaoGabaritoQuestaoFactory extends Factory
{
    protected $model = AvaliacaoGabaritoQuestao::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'avaliacao_gabarito_id' => AvaliacaoGabarito::factory(),
            'numero' => 1,
            'alternativa_correta' => 'A',
            'peso' => 1.00,
            'habilidade_id' => null,
            'anulada' => false,
        ];
    }
}
