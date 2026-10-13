<?php

declare(strict_types=1);

namespace Tests\Feature\Concerns;

use App\Models\Avaliacao;
use App\Models\CategoriaAvaliacao;
use App\Models\Disciplina;
use App\Models\EtapaAvaliativa;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Turma;

trait CriaAvaliacaoTestTrait
{
    /**
     * @param  array<string, mixed>  $atributos
     */
    protected function criarAvaliacao(array $atributos = []): Avaliacao
    {
        $turma = isset($atributos['turma_id']) ? Turma::find($atributos['turma_id']) : null;
        if (! $turma) {
            $periodo = PeriodoLetivo::factory()->create();
            $turma = Turma::factory()->create(['periodo_letivo_id' => $periodo->id]);
        }

        $disciplina = isset($atributos['disciplina_id']) ? Disciplina::find($atributos['disciplina_id']) : null;
        if (! $disciplina) {
            $disciplina = Disciplina::factory()->create();
        }

        $categoria = isset($atributos['categoria_avaliacao_id']) ? CategoriaAvaliacao::find($atributos['categoria_avaliacao_id']) : null;
        if (! $categoria) {
            $categoria = CategoriaAvaliacao::firstOrCreate(['nome' => 'Prova Teste'], ['eh_recuperacao' => false]);
        }

        $etapa = isset($atributos['etapa_avaliativa_id']) ? EtapaAvaliativa::find($atributos['etapa_avaliativa_id']) : null;
        if (! $etapa) {
            $etapa = EtapaAvaliativa::firstOrCreate([
                'turma_id' => $turma->id,
                'nome' => '1º Bimestre',
            ], [
                'periodo_letivo_id' => $turma->periodo_letivo_id,
                'data_inicio' => now()->startOfYear()->toDateString(),
                'data_fim' => now()->endOfYear()->toDateString(),
            ]);
        }

        $professor = isset($atributos['professor_id']) ? Pessoa::find($atributos['professor_id']) : null;
        if (! $professor && ! array_key_exists('professor_id', $atributos)) {
            $professor = Pessoa::factory()->create();
        }

        return Avaliacao::create(array_merge([
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'categoria_avaliacao_id' => $categoria->id,
            'etapa_avaliativa_id' => $etapa->id,
            'professor_id' => $professor?->id,
            'nota_maxima' => 10.00,
            'peso_etapa_avaliativa' => 1.00,
            'data_prevista' => now()->toDateString(),
            'data_ocorrencia' => now()->toDateString(),
            'data_limite_lancamento' => now()->addDays(30)->toDateString(),
        ], $atributos));
    }
}
