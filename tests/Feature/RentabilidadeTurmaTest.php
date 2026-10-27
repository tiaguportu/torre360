<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ApuracaoHoraAula;
use App\Models\Banco;
use App\Models\CentroCusto;
use App\Models\Contrato;
use App\Models\CronogramaAula;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Fatura;
use App\Models\ItemFatura;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\PlanoConta;
use App\Models\Serie;
use App\Models\TransacaoBancaria;
use App\Models\Turma;
use App\Models\Unidade;
use App\Services\RentabilidadeTurmaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

/**
 * Rentabilidade realizada por turma (Onda 77): receita das faturas do mês, custo docente da apuração dividido pelas aulas, custo direto
 * por centro de custo, rateio do indireto por critério e comparação com os meses anteriores.
 *
 * "Hoje" é segunda-feira, 26/10/2026.
 *
 * Cenário de outubro/2026 (as contas estão fechadas à mão):
 *  - turma A (EF, 2 alunos), B (EF, 1 aluno) e C (EM, 1 aluno);
 *  - faturado: A 2.000 (1.000 pagos, 1.000 em atraso), B 1.000 (pagos), C 800 (a vencer);
 *  - docente: o professor P custou 900 (2 aulas em A e 1 em B: A 600, B 300) e o Q custou 400 sem aulas no cronograma;
 *  - direto: 300 na sala A; 300 no centro do curso EF (A 200, B 100);
 *  - indireto: 1.200 de administração e 300 sem centro, mais os 400 do docente sem turma = 1.900;
 *  - fora do resultado das turmas: 5.000 de folha; sem classificação: 777 (não entra na conta).
 */
class RentabilidadeTurmaTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    private Turma $a;

    private Turma $b;

    private Turma $c;

    private Curso $cursoEf;

    private Unidade $unidade;

    private PlanoConta $despesa;

    private Banco $banco;

    private Pessoa $professorP;

    private Pessoa $professorQ;

    private Disciplina $disciplina;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-26 09:00:00'));

        $this->unidade = Unidade::create(['nome' => 'Unidade Central', 'situacao_funcionamento' => '1']);
        $this->cursoEf = Curso::create(['unidade_id' => $this->unidade->id, 'nome_externo' => 'Ensino Fundamental', 'nome_interno' => 'EF']);
        $cursoEm = Curso::create(['unidade_id' => $this->unidade->id, 'nome_externo' => 'Ensino Médio', 'nome_interno' => 'EM']);
        $periodo = PeriodoLetivo::create(['nome' => '2026', 'ano' => 2026, 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);

        $this->a = Turma::create(['nome' => 'Turma A', 'periodo_letivo_id' => $periodo->id, 'serie_id' => Serie::create(['curso_id' => $this->cursoEf->id, 'nome' => '8º Ano', 'sistema_avaliacao' => 'Nota'])->id]);
        $this->b = Turma::create(['nome' => 'Turma B', 'periodo_letivo_id' => $periodo->id, 'serie_id' => Serie::create(['curso_id' => $this->cursoEf->id, 'nome' => '9º Ano', 'sistema_avaliacao' => 'Nota'])->id]);
        $this->c = Turma::create(['nome' => 'Turma C', 'periodo_letivo_id' => $periodo->id, 'serie_id' => Serie::create(['curso_id' => $cursoEm->id, 'nome' => '1ª Série', 'sistema_avaliacao' => 'Nota'])->id]);

        $this->despesa = PlanoConta::create(['codigo' => '3.1', 'nome' => 'Despesas operacionais', 'tipo' => 'despesa']);
        $this->banco = Banco::create(['nome' => 'Banco Teste', 'is_active' => true]);
        $this->professorP = Pessoa::create(['nome' => 'Professor P']);
        $this->professorQ = Pessoa::create(['nome' => 'Professor Q']);
        $this->disciplina = Disciplina::create(['nome' => 'História', 'sigla' => 'HIST']);
    }

    private function servico(): RentabilidadeTurmaService
    {
        return app(RentabilidadeTurmaService::class);
    }

    /**
     * @return array{0: Matricula, 1: Contrato}
     */
    private function aluno(Turma $turma, string $nome, array $matricula = []): array
    {
        $matricula = Matricula::create($matricula + ['pessoa_id' => Pessoa::create(['nome' => $nome])->id, 'turma_id' => $turma->id, 'situacao' => 'ativa']);

        return [$matricula, Contrato::create(['matricula_id' => $matricula->id, 'valor_total' => 12000, 'data_aceite' => '2026-01-05'])];
    }

    private function fatura(Contrato $contrato, string $vencimento, float $valor, string $status = 'pendente', float $pago = 0.0): Fatura
    {
        $fatura = Fatura::create(['contrato_id' => $contrato->id, 'vencimento' => $vencimento, 'status' => $status]);
        ItemFatura::create(['fatura_id' => $fatura->id, 'descricao' => 'Mensalidade', 'quantidade' => 1, 'valor_unitario' => $valor, 'desconto' => 0, 'tipo_desconto' => 'absoluto']);

        if ($pago > 0) {
            TransacaoBancaria::create(['banco_id' => $this->banco->id, 'fatura_id' => $fatura->id, 'tipo' => 'entrada', 'valor' => $pago, 'data_transacao' => $vencimento, 'descricao' => 'Pagamento', 'conciliado' => true]);
        }

        return $fatura;
    }

    private function saida(float $valor, ?CentroCusto $centro, string $data = '2026-10-15', bool $classificada = true): void
    {
        TransacaoBancaria::create([
            'banco_id' => $this->banco->id,
            'plano_conta_id' => $classificada ? $this->despesa->id : null,
            'centro_custo_id' => $centro?->id,
            'tipo' => 'saida',
            'valor' => $valor,
            'data_transacao' => $data,
            'descricao' => 'Despesa',
            'conciliado' => true,
        ]);
    }

    private function aula(Pessoa $professor, Turma $turma, string $data): void
    {
        CronogramaAula::create(['turma_id' => $turma->id, 'disciplina_id' => $this->disciplina->id, 'pessoa_id' => $professor->id, 'data' => $data, 'hora_inicio' => '08:00:00', 'hora_fim' => '08:50:00']);
    }

    private function apuracao(Pessoa $professor, float $valor, int $ano = 2026, int $mes = 10): void
    {
        ApuracaoHoraAula::create(['pessoa_id' => $professor->id, 'ano' => $ano, 'mes' => $mes, 'valor_total_bruto' => $valor, 'status' => 'aberto']);
    }

    private function cenarioOutubro(): void
    {
        [, $contratoA1] = $this->aluno($this->a, 'Aluno A1');
        [, $contratoA2] = $this->aluno($this->a, 'Aluno A2');
        [, $contratoB] = $this->aluno($this->b, 'Aluno B1');
        [, $contratoC] = $this->aluno($this->c, 'Aluno C1');

        $this->fatura($contratoA1, '2026-10-10', 1000, 'pago', 1000);
        $this->fatura($contratoA2, '2026-10-10', 1000, 'atrasado');
        $this->fatura($contratoB, '2026-10-10', 1000, 'pago', 1000);
        $this->fatura($contratoC, '2026-10-30', 800);

        $this->apuracao($this->professorP, 900);
        $this->apuracao($this->professorQ, 400);
        $this->aula($this->professorP, $this->a, '2026-10-05');
        $this->aula($this->professorP, $this->a, '2026-10-12');
        $this->aula($this->professorP, $this->b, '2026-10-06');

        $salaA = CentroCusto::create(['nome' => 'Sala A', 'turma_id' => $this->a->id]);
        $cursoEf = CentroCusto::create(['nome' => 'Curso EF', 'curso_id' => $this->cursoEf->id]);
        $administracao = CentroCusto::create(['nome' => 'Administração']);
        $folha = CentroCusto::create(['nome' => 'Folha', 'fora_do_resultado_das_turmas' => true]);

        $this->saida(300, $salaA);
        $this->saida(300, $cursoEf);
        $this->saida(1200, $administracao);
        $this->saida(300, null);
        $this->saida(5000, $folha);
        $this->saida(777, null, classificada: false);
    }

    /**
     * @return array<int, array<string, mixed>> as linhas indexadas pelo nome da turma
     */
    private function linhas(string $criterio = 'aluno', ?int $unidade = null, int $mes = 10): array
    {
        return collect($this->servico()->calcular(2026, $mes, $criterio, $unidade)['linhas'])->keyBy('turma')->all();
    }

    // --- Receita -----------------------------------------------------------------------------------------------------

    public function test_receita_da_turma_e_faturado_recebido_e_em_atraso(): void
    {
        $this->cenarioOutubro();

        $l = $this->linhas();

        $this->assertEquals(2000.0, $l['Turma A']['faturado']);
        $this->assertEquals(1000.0, $l['Turma A']['recebido']);
        $this->assertEquals(1000.0, $l['Turma A']['em_atraso']);
        $this->assertEquals(1000.0, $l['Turma B']['faturado']);
        $this->assertEquals(0.0, $l['Turma B']['em_atraso']);
        $this->assertEquals(800.0, $l['Turma C']['faturado']);
        $this->assertEquals(0.0, $l['Turma C']['recebido']);
        $this->assertEquals(0.0, $l['Turma C']['em_atraso'], 'fatura a vencer não é atraso');
        $this->assertSame([2, 1, 1], [$l['Turma A']['alunos'], $l['Turma B']['alunos'], $l['Turma C']['alunos']]);
    }

    public function test_fatura_cancelada_e_de_outro_mes_nao_entram_e_desconto_ja_vem_liquido(): void
    {
        [, $contrato] = $this->aluno($this->a, 'Aluno');
        $this->fatura($contrato, '2026-10-10', 1000, 'cancelado');
        $this->fatura($contrato, '2026-09-10', 1000);
        $comBolsa = Fatura::create(['contrato_id' => $contrato->id, 'vencimento' => '2026-10-20', 'status' => 'pendente']);
        ItemFatura::create(['fatura_id' => $comBolsa->id, 'descricao' => 'Mensalidade', 'quantidade' => 1, 'valor_unitario' => 1000, 'desconto' => 25, 'tipo_desconto' => 'percentual']);

        $this->assertEquals(750.0, $this->linhas()['Turma A']['faturado']);
    }

    public function test_aluno_conta_pelo_periodo_de_atividade_no_mes(): void
    {
        $this->aluno($this->a, 'Ativo');
        $this->aluno($this->a, 'Saiu antes', ['data_desativacao' => '2026-09-20']);
        $this->aluno($this->a, 'Entra depois', ['data_ativacao' => '2026-11-03']);
        $this->aluno($this->a, 'Saiu no mês', ['data_desativacao' => '2026-10-08']);
        $this->aluno($this->a, 'Pendente', ['situacao' => 'pendente']);

        $this->assertSame(2, $this->linhas()['Turma A']['alunos']);
    }

    // --- Custos ------------------------------------------------------------------------------------------------------

    public function test_custo_docente_e_dividido_pela_proporcao_das_aulas_e_o_sem_turma_vai_ao_pool(): void
    {
        $this->cenarioOutubro();

        $r = $this->servico()->calcular(2026, 10);
        $l = collect($r['linhas'])->keyBy('turma');

        $this->assertEquals(600.0, $l['Turma A']['custo_docente']);
        $this->assertEquals(300.0, $l['Turma B']['custo_docente']);
        $this->assertEquals(0.0, $l['Turma C']['custo_docente']);
        $this->assertEquals(400.0, $r['docente_sem_turma']);
    }

    public function test_custo_direto_por_turma_e_por_curso_e_o_que_fica_fora(): void
    {
        $this->cenarioOutubro();

        $r = $this->servico()->calcular(2026, 10);
        $l = collect($r['linhas'])->keyBy('turma');

        $this->assertEquals(500.0, $l['Turma A']['custo_direto'], '300 da sala + 200 do curso (2 de 3 alunos)');
        $this->assertEquals(100.0, $l['Turma B']['custo_direto']);
        $this->assertEquals(0.0, $l['Turma C']['custo_direto']);
        $this->assertEquals(1900.0, $r['indireto_total'], '1.200 + 300 + 400 do docente sem turma');
        $this->assertEquals(5000.0, $r['fora_do_resultado']);
        $this->assertSame(['quantidade' => 1, 'valor' => 777.0], $r['sem_classificacao']);
    }

    public function test_despesa_que_nao_e_do_tipo_despesa_e_entrada_nao_contam(): void
    {
        $receita = PlanoConta::create(['codigo' => '1.1', 'nome' => 'Receitas', 'tipo' => 'receita']);
        TransacaoBancaria::create(['banco_id' => $this->banco->id, 'plano_conta_id' => $receita->id, 'tipo' => 'saida', 'valor' => 900, 'data_transacao' => '2026-10-10', 'descricao' => 'Estorno', 'conciliado' => true]);
        TransacaoBancaria::create(['banco_id' => $this->banco->id, 'plano_conta_id' => $this->despesa->id, 'tipo' => 'entrada', 'valor' => 400, 'data_transacao' => '2026-10-10', 'descricao' => 'Devolução', 'conciliado' => true]);

        $this->assertEquals(0.0, $this->servico()->calcular(2026, 10)['indireto_total']);
    }

    // --- Rateio e resultado ------------------------------------------------------------------------------------------

    public function test_rateio_por_aluno(): void
    {
        $this->cenarioOutubro();

        $r = $this->servico()->calcular(2026, 10, 'aluno');
        $l = collect($r['linhas'])->keyBy('turma');

        $this->assertEquals(950.0, $l['Turma A']['custo_indireto']);
        $this->assertEquals(475.0, $l['Turma B']['custo_indireto']);
        $this->assertEquals(475.0, $l['Turma C']['custo_indireto']);

        $this->assertEquals(2050.0, $l['Turma A']['custo_total']);
        $this->assertEquals(-50.0, $l['Turma A']['resultado']);
        $this->assertEquals(-2.5, $l['Turma A']['margem']);
        $this->assertSame('negativa', $l['Turma A']['situacao']);
        $this->assertEquals(125.0, $l['Turma B']['resultado']);
        $this->assertEquals(12.5, $l['Turma B']['margem']);
        $this->assertEquals(325.0, $l['Turma C']['resultado']);
        $this->assertEquals(40.6, $l['Turma C']['margem']);
        $this->assertEquals(1025.0, $l['Turma A']['custo_por_aluno']);
        $this->assertEquals(-25.0, $l['Turma A']['resultado_por_aluno']);
    }

    public function test_rateio_igual_por_turma_e_por_hora_aula(): void
    {
        $this->cenarioOutubro();

        $igual = collect($this->servico()->calcular(2026, 10, 'turma')['linhas'])->keyBy('turma');
        $this->assertEquals(633.33, $igual['Turma A']['custo_indireto']);
        $this->assertEquals(633.33, $igual['Turma C']['custo_indireto']);

        $horas = collect($this->servico()->calcular(2026, 10, 'hora_aula')['linhas'])->keyBy('turma');
        $this->assertEquals(1266.67, $horas['Turma A']['custo_indireto'], '2 das 3 aulas do mês');
        $this->assertEquals(633.33, $horas['Turma B']['custo_indireto']);
        $this->assertEquals(0.0, $horas['Turma C']['custo_indireto']);
    }

    public function test_o_criterio_muda_o_resultado_mas_nao_o_custo_total_da_escola(): void
    {
        $this->cenarioOutubro();

        $totais = [];

        foreach (array_keys(RentabilidadeTurmaService::CRITERIOS) as $criterio) {
            $totais[$criterio] = $this->servico()->calcular(2026, 10, $criterio)['totais'];
        }

        // 900 de docente + 600 de direto + 1.900 de indireto = 3.400 em qualquer critério.
        foreach ($totais as $criterio => $soma) {
            $this->assertEqualsWithDelta(3400.0, $soma['custo_total'], 0.02, $criterio);
            $this->assertEquals(3800.0, $soma['faturado']);
        }

        $this->assertSame('Por aluno', $this->servico()->calcular(2026, 10, 'aluno')['criterio_nome']);
        $this->assertSame('aluno', $this->servico()->calcular(2026, 10, 'inexistente')['criterio']);
    }

    public function test_as_linhas_saem_do_pior_resultado_para_o_melhor(): void
    {
        $this->cenarioOutubro();

        $this->assertSame(['Turma A', 'Turma B', 'Turma C'], array_column($this->servico()->calcular(2026, 10)['linhas'], 'turma'));
    }

    public function test_turma_sem_receita_e_sem_custo_fica_sem_movimento_e_sem_margem(): void
    {
        $l = $this->linhas();

        $this->assertSame('sem_movimento', $l['Turma A']['situacao']);
        $this->assertNull($l['Turma A']['margem']);
        $this->assertNull($l['Turma A']['custo_por_aluno']);
    }

    public function test_turma_fora_do_periodo_letivo_cancelada_ou_planejada_nao_aparece(): void
    {
        $outroPeriodo = PeriodoLetivo::create(['nome' => '2025', 'ano' => 2025, 'data_inicio' => '2025-02-01', 'data_fim' => '2025-12-15']);
        Turma::create(['nome' => 'Turma 2025', 'periodo_letivo_id' => $outroPeriodo->id, 'serie_id' => $this->a->serie_id]);
        Turma::create(['nome' => 'Turma cancelada', 'periodo_letivo_id' => $this->a->periodo_letivo_id, 'serie_id' => $this->a->serie_id, 'status' => 'cancelada']);
        Turma::create(['nome' => 'Turma planejada', 'periodo_letivo_id' => $this->a->periodo_letivo_id, 'serie_id' => $this->a->serie_id, 'status' => 'planejada']);

        $this->assertSame(['Turma A', 'Turma B', 'Turma C'], collect($this->servico()->calcular(2026, 10)['linhas'])->pluck('turma')->sort()->values()->all());
    }

    public function test_filtro_de_unidade_mantem_o_rateio_global(): void
    {
        $this->cenarioOutubro();
        $outra = Unidade::create(['nome' => 'Outra unidade', 'situacao_funcionamento' => '1']);
        $cursoOutra = Curso::create(['unidade_id' => $outra->id, 'nome_externo' => 'Curso X', 'nome_interno' => 'X']);
        $turmaOutra = Turma::create(['nome' => 'Turma X', 'periodo_letivo_id' => $this->a->periodo_letivo_id, 'serie_id' => Serie::create(['curso_id' => $cursoOutra->id, 'nome' => 'S', 'sistema_avaliacao' => 'Nota'])->id]);
        $this->aluno($turmaOutra, 'Aluno X');

        $so = $this->linhas('aluno', $outra->id);

        $this->assertSame(['Turma X'], array_keys($so));
        // O pool de 1.900 divide por 5 alunos (2+1+1+1): 380 para a turma X, mesmo filtrando a unidade.
        $this->assertEquals(380.0, $so['Turma X']['custo_indireto']);
    }

    // --- Comparações e grupos ----------------------------------------------------------------------------------------

    public function test_compara_com_o_mes_anterior_e_o_ano_anterior(): void
    {
        $this->cenarioOutubro();
        [, $contrato] = $this->aluno($this->b, 'Aluno B2');
        $this->fatura($contrato, '2026-09-10', 600, 'pago', 600);

        $comparado = $this->servico()->comparar(2026, 10);
        $b = collect($comparado['linhas'])->keyBy('turma')['Turma B'];

        $this->assertEquals(600.0, $b['mes_anterior']['resultado']);
        $this->assertEquals(100.0, $b['mes_anterior']['margem']);
        $this->assertNull($b['ano_anterior'], 'em 2025 a turma não existia');
        $this->assertEquals(600.0, $comparado['totais_mes_anterior']['faturado']);
        $this->assertArrayHasKey('planejado_margem', $b);
        $this->assertArrayHasKey('planejado_resultado', $b);
    }

    public function test_agrupa_por_curso_serie_e_unidade(): void
    {
        $this->cenarioOutubro();
        $linhas = $this->servico()->calcular(2026, 10)['linhas'];

        $porCurso = collect($this->servico()->agrupar($linhas, 'curso'))->keyBy('nome');
        $this->assertEquals(3000.0, $porCurso['Ensino Fundamental']['faturado']);
        $this->assertSame(2, $porCurso['Ensino Fundamental']['turmas']);
        $this->assertEquals(2925.0, $porCurso['Ensino Fundamental']['custo_total']);
        $this->assertEquals(75.0, $porCurso['Ensino Fundamental']['resultado']);
        $this->assertEquals(2.5, $porCurso['Ensino Fundamental']['margem']);

        $this->assertCount(1, $this->servico()->agrupar($linhas, 'unidade'));
        $this->assertCount(3, $this->servico()->agrupar($linhas, 'serie'));
        $this->assertSame($linhas, $this->servico()->agrupar($linhas, 'turma'));
    }

    // --- Margem negativa seguida (Mesa) ------------------------------------------------------------------------------

    public function test_turma_com_resultado_negativo_em_todos_os_ultimos_meses(): void
    {
        $salaA = CentroCusto::create(['nome' => 'Sala A', 'turma_id' => $this->a->id]);
        $salaB = CentroCusto::create(['nome' => 'Sala B', 'turma_id' => $this->b->id]);
        [, $contratoA] = $this->aluno($this->a, 'Aluno A');
        [, $contratoB] = $this->aluno($this->b, 'Aluno B');

        foreach (['2026-08', '2026-09'] as $mes) {
            $this->fatura($contratoA, "{$mes}-10", 500, 'pago', 500);
            $this->saida(800, $salaA, "{$mes}-15");
            $this->fatura($contratoB, "{$mes}-10", 500, 'pago', 500);
        }

        // Em B, agosto foi negativo e setembro não: não conta como "sempre negativa".
        $this->saida(900, $salaB, '2026-08-20');

        $lista = $this->servico()->comResultadoNegativoHa(2);

        $this->assertSame(['Turma A'], array_column($lista, 'turma'));
        $this->assertEquals([-300.0, -300.0], $lista[0]['resultados']);
        $this->assertSame([], $this->servico()->comResultadoNegativoHa(3), 'em julho a turma A não teve receita nem custo');
    }
}
