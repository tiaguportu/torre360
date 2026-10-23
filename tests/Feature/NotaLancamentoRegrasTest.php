<?php

namespace Tests\Feature;

use App\Enums\SituacaoNota;
use App\Models\Avaliacao;
use App\Models\CategoriaAvaliacao;
use App\Models\Disciplina;
use App\Models\EtapaAvaliativa;
use App\Models\Matricula;
use App\Models\Nota;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Turma;
use App\Services\NotaLancamentoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotaLancamentoRegrasTest extends TestCase
{
    use RefreshDatabase;

    private Turma $turma;

    private Avaliacao $avaliacao;

    private Matricula $matriculaA;

    private Matricula $matriculaB;

    private NotaLancamentoService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $periodo = PeriodoLetivo::factory()->create();
        $this->turma = Turma::factory()->create(['periodo_letivo_id' => $periodo->id]);

        $disciplina = Disciplina::factory()->create(['nome' => 'Matemática']);
        $categoria = CategoriaAvaliacao::create(['nome' => 'Prova Bimestral', 'eh_recuperacao' => false]);
        $etapa = EtapaAvaliativa::create([
            'turma_id' => $this->turma->id,
            'periodo_letivo_id' => $periodo->id,
            'nome' => '1o Bimestre',
            'data_inicio' => now()->startOfYear()->toDateString(),
            'data_fim' => now()->endOfYear()->toDateString(),
        ]);

        $this->avaliacao = Avaliacao::create([
            'turma_id' => $this->turma->id,
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

        $alunoA = Pessoa::factory()->create(['nome' => 'Ana Silva']);
        $alunoB = Pessoa::factory()->create(['nome' => 'Bruno Costa']);

        $this->matriculaA = Matricula::factory()->create([
            'pessoa_id' => $alunoA->id,
            'turma_id' => $this->turma->id,
            'situacao' => 'ativa',
        ]);

        $this->matriculaB = Matricula::factory()->create([
            'pessoa_id' => $alunoB->id,
            'turma_id' => $this->turma->id,
            'situacao' => 'ativa',
        ]);

        $this->service = app(NotaLancamentoService::class);
    }

    public function test_estado_notas_para_grade_retorna_alunos_ordenados_com_valores_e_situacoes(): void
    {
        Nota::create([
            'avaliacao_id' => $this->avaliacao->id,
            'matricula_id' => $this->matriculaA->id,
            'valor' => 8.5,
        ]);

        Nota::create([
            'avaliacao_id' => $this->avaliacao->id,
            'matricula_id' => $this->matriculaB->id,
            'situacao' => SituacaoNota::FALTOU,
        ]);

        $grade = $this->service->estadoNotasParaGrade($this->avaliacao);

        $this->assertCount(2, $grade);

        $this->assertEquals($this->matriculaA->id, $grade[0]['matricula_id']);
        $this->assertEquals('Ana Silva', $grade[0]['aluno_nome']);
        $this->assertEquals(8.5, $grade[0]['valor']);
        $this->assertNull($grade[0]['situacao']);

        $this->assertEquals($this->matriculaB->id, $grade[1]['matricula_id']);
        $this->assertEquals('Bruno Costa', $grade[1]['aluno_nome']);
        $this->assertNull($grade[1]['valor']);
        $this->assertEquals('faltou', $grade[1]['situacao']);
    }

    public function test_salvar_notas_persiste_valores_e_permite_atualizacoes(): void
    {
        $notasPayload = [
            [
                'matricula_id' => $this->matriculaA->id,
                'aluno_nome' => 'Ana Silva',
                'valor' => '9.5',
            ],
            [
                'matricula_id' => $this->matriculaB->id,
                'aluno_nome' => 'Bruno Costa',
                'valor' => '7.0',
            ],
        ];

        $this->service->salvarNotas($this->avaliacao, $notasPayload);

        $this->assertDatabaseHas('nota', [
            'avaliacao_id' => $this->avaliacao->id,
            'matricula_id' => $this->matriculaA->id,
            'valor' => 9.5,
        ]);

        $this->assertDatabaseHas('nota', [
            'avaliacao_id' => $this->avaliacao->id,
            'matricula_id' => $this->matriculaB->id,
            'valor' => 7.0,
        ]);
    }

    public function test_justificativa_prevalece_sobre_valor_digitado(): void
    {
        $notasPayload = [
            [
                'matricula_id' => $this->matriculaA->id,
                'aluno_nome' => 'Ana Silva',
                'valor' => '8.0',
                'situacao' => 'faltou',
            ],
        ];

        $this->service->salvarNotas($this->avaliacao, $notasPayload);

        $nota = Nota::where('avaliacao_id', $this->avaliacao->id)
            ->where('matricula_id', $this->matriculaA->id)
            ->first();

        $this->assertNotNull($nota);
        $this->assertNull($nota->valor, 'O valor numérico deve ser anulado quando há justificativa.');
        $this->assertEquals(SituacaoNota::FALTOU, $nota->situacao);
    }

    public function test_limpar_valor_e_situacao_remove_a_nota(): void
    {
        Nota::create([
            'avaliacao_id' => $this->avaliacao->id,
            'matricula_id' => $this->matriculaA->id,
            'valor' => 10.0,
        ]);

        $notasPayload = [
            [
                'matricula_id' => $this->matriculaA->id,
                'aluno_nome' => 'Ana Silva',
                'valor' => '',
                'situacao' => '',
            ],
        ];

        $this->service->salvarNotas($this->avaliacao, $notasPayload);

        $this->assertDatabaseMissing('nota', [
            'avaliacao_id' => $this->avaliacao->id,
            'matricula_id' => $this->matriculaA->id,
        ]);
    }

    public function test_lanca_excecao_quando_nota_excede_nota_maxima_da_avaliacao(): void
    {
        $notasPayload = [
            [
                'matricula_id' => $this->matriculaA->id,
                'aluno_nome' => 'Ana Silva',
                'valor' => '11.5',
            ],
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A nota (11.5) de Ana Silva não pode ser maior que a nota máxima da avaliação (10.00).');

        $this->service->salvarNotas($this->avaliacao, $notasPayload);
    }

    public function test_estado_notas_para_grade_retorna_alunos_sem_erro_quando_nenhuma_nota_existe(): void
    {
        // Garante que nenhuma nota existe previamente
        Nota::where('avaliacao_id', $this->avaliacao->id)->delete();

        $grade = $this->service->estadoNotasParaGrade($this->avaliacao);

        $this->assertCount(2, $grade);
        $this->assertNull($grade[0]['valor']);
        $this->assertNull($grade[0]['situacao']);
        $this->assertNull($grade[1]['valor']);
        $this->assertNull($grade[1]['situacao']);
    }

    public function test_salvar_nota_com_situacao_nao_se_aplica_anula_valor_e_persiste_situacao(): void
    {
        $notasPayload = [
            [
                'matricula_id' => $this->matriculaA->id,
                'aluno_nome' => 'Ana Silva',
                'valor' => '9.0',
                'situacao' => 'nao_se_aplica',
            ],
        ];

        $this->service->salvarNotas($this->avaliacao, $notasPayload);

        $nota = Nota::where('avaliacao_id', $this->avaliacao->id)
            ->where('matricula_id', $this->matriculaA->id)
            ->first();

        $this->assertNotNull($nota);
        $this->assertNull($nota->valor, 'O valor numérico deve ser nulo quando justificado como não se aplica.');
        $this->assertEquals(SituacaoNota::NAO_SE_APLICA, $nota->situacao);
    }

    public function test_enum_situacao_nota_retorna_rotulo_e_cor(): void
    {
        $this->assertSame('Faltou', SituacaoNota::FALTOU->getLabel());
        $this->assertSame('warning', SituacaoNota::FALTOU->getColor());

        $this->assertSame('Não se aplica', SituacaoNota::NAO_SE_APLICA->getLabel());
        $this->assertSame('gray', SituacaoNota::NAO_SE_APLICA->getColor());
    }
}
