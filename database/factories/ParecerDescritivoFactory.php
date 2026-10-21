<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StatusParecerDescritivo;
use App\Models\EtapaAvaliativa;
use App\Models\Matricula;
use App\Models\ParecerDescritivo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParecerDescritivo>
 */
class ParecerDescritivoFactory extends Factory
{
    protected $model = ParecerDescritivo::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'matricula_id' => Matricula::factory(),
            'etapa_avaliativa_id' => fn (array $atributos): int => EtapaAvaliativa::create([
                'turma_id' => Matricula::find($atributos['matricula_id'])->turma_id,
                'nome' => '1º Trimestre',
                'periodo_letivo_id' => Matricula::find($atributos['matricula_id'])->turma->periodo_letivo_id,
                'data_inicio' => now()->startOfYear()->toDateString(),
                'data_fim' => now()->endOfYear()->toDateString(),
            ])->id,
            'texto' => 'Durante o trimestre, [ALUNO] participou das atividades com curiosidade e envolvimento.',
            'status' => StatusParecerDescritivo::Rascunho,
            'gerado_com_ia' => false,
            'autor_user_id' => null,
            'publicado_por_user_id' => null,
            'publicado_em' => null,
        ];
    }

    public function publicado(): static
    {
        return $this->state(fn (): array => [
            'status' => StatusParecerDescritivo::Publicado,
            'publicado_em' => now(),
        ]);
    }

    public function emRevisao(): static
    {
        return $this->state(fn (): array => ['status' => StatusParecerDescritivo::EmRevisao]);
    }
}
