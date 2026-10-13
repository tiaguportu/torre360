<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Avaliacao;
use App\Models\CategoriaAvaliacao;
use App\Models\Disciplina;
use App\Models\EtapaAvaliativa;
use App\Models\GabaritoFolha;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Turma;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GabaritoFolha>
 */
class GabaritoFolhaFactory extends Factory
{
    protected $model = GabaritoFolha::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'avaliacao_id' => function (): int {
                $periodo = PeriodoLetivo::factory()->create();
                $turma = Turma::factory()->create(['periodo_letivo_id' => $periodo->id]);
                $disciplina = Disciplina::factory()->create();
                $categoria = CategoriaAvaliacao::firstOrCreate(['nome' => 'Prova Teste'], ['eh_recuperacao' => false]);
                $etapa = EtapaAvaliativa::create([
                    'turma_id' => $turma->id,
                    'periodo_letivo_id' => $periodo->id,
                    'nome' => '1º Bimestre',
                    'data_inicio' => now()->startOfYear()->toDateString(),
                    'data_fim' => now()->endOfYear()->toDateString(),
                ]);

                return Avaliacao::create([
                    'turma_id' => $turma->id,
                    'disciplina_id' => $disciplina->id,
                    'categoria_avaliacao_id' => $categoria->id,
                    'etapa_avaliativa_id' => $etapa->id,
                    'nota_maxima' => 10.00,
                    'peso_etapa_avaliativa' => 1.00,
                    'data_prevista' => now()->toDateString(),
                ])->id;
            },
            'matricula_id' => Matricula::factory(),
            'layout_versao' => 1,
            'gerada_por_user_id' => null,
            'corrigida_em' => null,
        ];
    }
}
