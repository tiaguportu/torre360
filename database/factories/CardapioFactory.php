<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Alergeno;
use App\Enums\TipoRefeicaoCardapio;
use App\Models\Cardapio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cardapio>
 */
class CardapioFactory extends Factory
{
    protected $model = Cardapio::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'data' => now()->toDateString(),
            'tipo_refeicao' => TipoRefeicaoCardapio::Almoco,
            'descricao' => 'Arroz, feijão, frango grelhado e salada de alface',
            'alergenos' => [],
            'unidade_id' => null,
            'publicado' => true,
            'observacoes' => null,
            'criado_por_user_id' => null,
        ];
    }

    public function rascunho(): static
    {
        return $this->state(fn (): array => ['publicado' => false]);
    }

    /**
     * @param  list<Alergeno>  $alergenos
     */
    public function comAlergenos(array $alergenos): static
    {
        return $this->state(fn (): array => ['alergenos' => array_map(fn (Alergeno $a): string => $a->value, $alergenos)]);
    }
}
