<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ModoAnulacaoQuestao;
use App\Models\Avaliacao;
use App\Models\AvaliacaoGabarito;
use App\Models\CategoriaAvaliacao;
use App\Models\Disciplina;
use App\Models\EtapaAvaliativa;
use App\Models\PeriodoLetivo;
use App\Models\Turma;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AvaliacaoGabarito>
 */
class AvaliacaoGabaritoFactory extends Factory
{
    protected $model = AvaliacaoGabarito::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
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
            'total_questoes' => 10,
            'alternativas' => 5,
            'layout_versao' => 1,
            'layout' => null,
            'anulacao_modo' => ModoAnulacaoQuestao::PontuaTodos,
            'travado_em' => null,
        ];
    }
}
