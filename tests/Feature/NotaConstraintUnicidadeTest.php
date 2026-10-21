<?php

namespace Tests\Feature;

use App\Models\Avaliacao;
use App\Models\CategoriaAvaliacao;
use App\Models\Disciplina;
use App\Models\EtapaAvaliativa;
use App\Models\Matricula;
use App\Models\Nota;
use App\Models\Pessoa;
use App\Models\Turma;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotaConstraintUnicidadeTest extends TestCase
{
    use RefreshDatabase;

    public function test_impede_duplicidade_de_nota_para_mesma_matricula_e_avaliacao(): void
    {
        $turma = Turma::factory()->create();

        $disciplina = Disciplina::factory()->create(['nome' => 'Matemática']);
        $categoria = CategoriaAvaliacao::create(['nome' => 'Prova Bimestral', 'eh_recuperacao' => false]);
        $etapa = EtapaAvaliativa::create([
            'turma_id' => $turma->id,
            'periodo_letivo_id' => $turma->periodo_letivo_id,
            'nome' => '1º Bimestre',
            'data_inicio' => now()->startOfYear()->toDateString(),
            'data_fim' => now()->endOfYear()->toDateString(),
        ]);

        $avaliacao = Avaliacao::create([
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'categoria_avaliacao_id' => $categoria->id,
            'etapa_avaliativa_id' => $etapa->id,
            'professor_id' => Pessoa::factory()->create()->id,
            'nota_maxima' => 10.0,
            'peso_etapa_avaliativa' => 1.0,
            'data_prevista' => now()->toDateString(),
            'data_ocorrencia' => now()->toDateString(),
            'data_limite_lancamento' => now()->addDays(20)->toDateString(),
        ]);

        $aluno = Pessoa::factory()->create();
        $matricula = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'status' => 'ativa',
        ]);

        // Primeira nota: salva com sucesso
        Nota::create([
            'matricula_id' => $matricula->id,
            'avaliacao_id' => $avaliacao->id,
            'valor' => 8.5,
        ]);

        // Segunda nota com mesma matrícula e avaliação: deve disparar QueryException por violação de unicidade
        $this->expectException(QueryException::class);

        Nota::create([
            'matricula_id' => $matricula->id,
            'avaliacao_id' => $avaliacao->id,
            'valor' => 9.0,
        ]);
    }
}
