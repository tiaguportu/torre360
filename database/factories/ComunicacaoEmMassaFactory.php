<?php

namespace Database\Factories;

use App\Enums\StatusComunicacaoEmMassa;
use App\Enums\TipoPublicoComunicacao;
use App\Models\ComunicacaoEmMassa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComunicacaoEmMassa>
 */
class ComunicacaoEmMassaFactory extends Factory
{
    protected $model = ComunicacaoEmMassa::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => 'Comunicação '.$this->faker->unique()->words(2, true),
            'tipo_publico' => TipoPublicoComunicacao::Interessados,
            'filtros' => [],
            'canal' => 'email',
            'assunto' => $this->faker->sentence(4),
            'corpo' => '<p>'.$this->faker->paragraph().'</p>',
            'status' => StatusComunicacaoEmMassa::Rascunho,
        ];
    }

    public function paraInteressados(array $filtros): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo_publico' => TipoPublicoComunicacao::Interessados,
            'filtros' => $filtros,
        ]);
    }

    public function paraResponsaveisDaTurma(array $turmaIds): static
    {
        return $this->state(fn (array $attributes) => [
            'tipo_publico' => TipoPublicoComunicacao::ResponsaveisTurma,
            'filtros' => ['turma_ids' => $turmaIds],
        ]);
    }
}
