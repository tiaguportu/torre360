<?php

namespace Tests\Feature;

use App\Models\Avaliacao;
use App\Models\CategoriaAvaliacao;
use App\Models\Disciplina;
use App\Models\EtapaAvaliativa;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Turma;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AvaliacaoConstraintTest extends TestCase
{
    use DatabaseTransactions;

    private Turma $turma;

    private Disciplina $disciplina;

    private EtapaAvaliativa $etapa;

    private CategoriaAvaliacao $categoria;

    private Pessoa $professor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->turma = Turma::first() ?? Turma::create(['nome' => 'Turma Teste Manual']);

        $this->disciplina = Disciplina::first() ?? Disciplina::create(['nome' => 'Disciplina Teste Manual']);

        $periodo = PeriodoLetivo::first() ?? PeriodoLetivo::create([
            'nome' => '2026',
            'inicio' => '2026-01-01',
            'fim' => '2026-12-31',
        ]);

        $this->etapa = EtapaAvaliativa::first() ?? EtapaAvaliativa::create([
            'nome' => '1º Bimestre Teste',
            'periodo_letivo_id' => $periodo->id,
        ]);

        $this->categoria = CategoriaAvaliacao::first() ?? CategoriaAvaliacao::create(['nome' => 'Categoria Teste Manual']);

        $this->professor = Pessoa::first() ?? Pessoa::create(['nome' => 'Professor Teste Manual']);
    }

    /** @test */
    public function nao_deve_permitir_criar_duas_avaliacoes_com_a_mesma_combinacao_com_professor()
    {
        // Cria a primeira avaliação
        Avaliacao::create([
            'turma_id' => $this->turma->id,
            'disciplina_id' => $this->disciplina->id,
            'etapa_avaliativa_id' => $this->etapa->id,
            'categoria_avaliacao_id' => $this->categoria->id,
            'professor_id' => $this->professor->id,
            'data_prevista' => now(),
            'data_limite_lancamento' => now()->addDays(15),
            'nota_maxima' => 10,
            'peso_etapa_avaliativa' => 1,
        ]);

        // Tenta criar a segunda idêntica - deve lançar ValidationException pelas regras de negócio
        $this->expectException(ValidationException::class);

        Avaliacao::create([
            'turma_id' => $this->turma->id,
            'disciplina_id' => $this->disciplina->id,
            'etapa_avaliativa_id' => $this->etapa->id,
            'categoria_avaliacao_id' => $this->categoria->id,
            'professor_id' => $this->professor->id,
            'data_prevista' => now(),
            'data_limite_lancamento' => now()->addDays(15),
            'nota_maxima' => 10,
            'peso_etapa_avaliativa' => 1,
        ]);
    }

    /** @test */
    public function nao_deve_permitir_criar_duas_avaliacoes_com_a_mesma_combinacao_com_professor_nulo()
    {
        // Cria a primeira avaliação com professor nulo
        Avaliacao::create([
            'turma_id' => $this->turma->id,
            'disciplina_id' => $this->disciplina->id,
            'etapa_avaliativa_id' => $this->etapa->id,
            'categoria_avaliacao_id' => $this->categoria->id,
            'professor_id' => null,
            'data_prevista' => now(),
            'data_limite_lancamento' => now()->addDays(15),
            'nota_maxima' => 10,
            'peso_etapa_avaliativa' => 1,
        ]);

        // Tenta criar a segunda idêntica com professor nulo - deve lançar ValidationException pelas regras de negócio
        $this->expectException(ValidationException::class);

        Avaliacao::create([
            'turma_id' => $this->turma->id,
            'disciplina_id' => $this->disciplina->id,
            'etapa_avaliativa_id' => $this->etapa->id,
            'categoria_avaliacao_id' => $this->categoria->id,
            'professor_id' => null,
            'data_prevista' => now(),
            'data_limite_lancamento' => now()->addDays(15),
            'nota_maxima' => 10,
            'peso_etapa_avaliativa' => 1,
        ]);
    }

    /** @test */
    public function deve_permitir_salvar_avaliacao_existente_sem_erro_de_duplicidade()
    {
        $avaliacao = Avaliacao::create([
            'turma_id' => $this->turma->id,
            'disciplina_id' => $this->disciplina->id,
            'etapa_avaliativa_id' => $this->etapa->id,
            'categoria_avaliacao_id' => $this->categoria->id,
            'professor_id' => $this->professor->id,
            'data_prevista' => now(),
            'data_limite_lancamento' => now()->addDays(15),
            'nota_maxima' => 10,
            'peso_etapa_avaliativa' => 1,
        ]);

        // Atualiza outro campo (como data_prevista) - não deve lançar exceção
        $avaliacao->data_prevista = now()->addDay();
        $this->assertTrue($avaliacao->save());
    }

    /** @test */
    public function deve_lancar_query_exception_no_banco_se_ignorar_eventos_do_eloquent()
    {
        // Cria a primeira avaliação sem disparar eventos
        Avaliacao::withoutEvents(function () {
            Avaliacao::create([
                'turma_id' => $this->turma->id,
                'disciplina_id' => $this->disciplina->id,
                'etapa_avaliativa_id' => $this->etapa->id,
                'categoria_avaliacao_id' => $this->categoria->id,
                'professor_id' => $this->professor->id,
                'data_prevista' => now(),
                'data_limite_lancamento' => now()->addDays(15),
                'nota_maxima' => 10,
                'peso_etapa_avaliativa' => 1,
            ]);
        });

        // Tenta criar a segunda duplicada sem disparar eventos. O banco MySQL deve lançar QueryException devido à restrição unique
        $this->expectException(QueryException::class);

        Avaliacao::withoutEvents(function () {
            Avaliacao::create([
                'turma_id' => $this->turma->id,
                'disciplina_id' => $this->disciplina->id,
                'etapa_avaliativa_id' => $this->etapa->id,
                'categoria_avaliacao_id' => $this->categoria->id,
                'professor_id' => $this->professor->id,
                'data_prevista' => now(),
                'data_limite_lancamento' => now()->addDays(15),
                'nota_maxima' => 10,
                'peso_etapa_avaliativa' => 1,
            ]);
        });
    }
}
