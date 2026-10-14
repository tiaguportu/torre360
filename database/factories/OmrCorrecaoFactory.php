<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StatusOmrCorrecao;
use App\Models\Avaliacao;
use App\Models\CategoriaAvaliacao;
use App\Models\Disciplina;
use App\Models\EtapaAvaliativa;
use App\Models\Matricula;
use App\Models\OmrCorrecao;
use App\Models\PeriodoLetivo;
use App\Models\Turma;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OmrCorrecao>
 */
class OmrCorrecaoFactory extends Factory
{
    protected $model = OmrCorrecao::class;

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
                    'data_ocorrencia' => now()->toDateString(),
                    'data_limite_lancamento' => now()->addDays(30)->toDateString(),
                ])->id;
            },
            'gabarito_folha_id' => null,
            'matricula_id' => Matricula::factory(),
            'user_id' => null,
            'arquivo_path' => 'omr/temp.jpg',
            'arquivo_sha256' => hash('sha256', 'fake-image-content'),
            'status' => StatusOmrCorrecao::AguardandoConferencia,
            'respostas' => [],
            'respostas_finais' => null,
            'alertas' => null,
            'acertos' => 8,
            'total_validas' => 10,
            'nota_calculada' => 8.00,
            'motor' => 'gd',
            'layout_versao' => 1,
            'tempo_ms' => 120,
            'confirmada_por_user_id' => null,
            'confirmada_em' => null,
            'mensagem_erro' => null,
        ];
    }
}
