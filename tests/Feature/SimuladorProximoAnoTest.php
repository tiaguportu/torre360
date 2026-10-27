<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatusListaEspera;
use App\Models\CenarioProximoAno;
use App\Models\Contrato;
use App\Models\Curso;
use App\Models\ListaEsperaMatricula;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\PeriodoRematricula;
use App\Models\PeriodoRematriculaPreco;
use App\Models\Pessoa;
use App\Models\Rematricula;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\Unidade;
use App\Models\User;
use App\Services\SimuladorProximoAnoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

/**
 * Simulador do próximo ano letivo (Onda 76), regras: o que entra na projeção por série, o "e se", o ponto de equilíbrio, as sugestões
 * da campanha anterior, os cenários salvos e os alertas da Minha Mesa.
 *
 * "Hoje" é segunda-feira, 26/10/2026. A campanha "Rematrícula 2027" vai de 01/10 a 15/11.
 *
 * Cenário (as contas estão fechadas à mão), 10 alunos ativos em 2026 com contrato de R$ 12.000 (R$ 1.000 por mês):
 *  - a1 a a3: rematrícula confirmada no 7º ano;
 *  - a4 e a5: rematrícula em andamento no 7º ano;
 *  - a6: em andamento no 8º ano, com risco de evasão 70 (sai da conta);
 *  - a7: recusou (rematrícula cancelada);
 *  - a8: em andamento, ainda sem série de destino;
 *  - a9 (risco 10) e a10 (risco 80): nem iniciaram.
 * Em 2027: o 7º ano tem uma turma de 4 vagas (custo planejado de R$ 3.600) e o 8º ano, uma de 30. O 6º ano não tem turma.
 * Tabela de preços: 7º ano R$ 14.400 (1.200 por mês) e 8º ano R$ 15.600 (1.300 por mês). Lista de espera: 3 no 7º ano e 1 no 8º.
 */
class SimuladorProximoAnoTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    private PeriodoRematricula $campanha;

    private User $solicitante;

    private PeriodoLetivo $origem;

    private PeriodoLetivo $destino;

    private Serie $setimo;

    private Serie $oitavo;

    private Serie $sexto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-26 09:00:00'));

        $this->solicitante = User::factory()->create();
        $unidade = Unidade::create(['nome' => 'Unidade Central', 'situacao_funcionamento' => '1']);
        $curso = Curso::create(['unidade_id' => $unidade->id, 'nome_externo' => 'Ensino Fundamental', 'nome_interno' => 'EF']);
        $this->sexto = Serie::create(['curso_id' => $curso->id, 'nome' => '6º Ano', 'sistema_avaliacao' => 'Nota']);
        $this->setimo = Serie::create(['curso_id' => $curso->id, 'nome' => '7º Ano', 'sistema_avaliacao' => 'Nota']);
        $this->oitavo = Serie::create(['curso_id' => $curso->id, 'nome' => '8º Ano', 'sistema_avaliacao' => 'Nota']);

        $this->origem = PeriodoLetivo::create(['nome' => '2026', 'ano' => 2026, 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);
        $this->destino = PeriodoLetivo::create(['nome' => '2027', 'ano' => 2027, 'data_inicio' => '2027-02-01', 'data_fim' => '2027-12-15']);
        $this->campanha = PeriodoRematricula::create(['nome' => 'Rematrícula 2027', 'periodo_letivo_origem_id' => $this->origem->id, 'periodo_letivo_destino_id' => $this->destino->id, 'data_inicio' => '2026-10-01', 'data_fim' => '2026-11-15', 'is_ativo' => true]);

        PeriodoRematriculaPreco::create(['periodo_rematricula_id' => $this->campanha->id, 'serie_id' => $this->setimo->id, 'valor_total' => 14400]);
        PeriodoRematriculaPreco::create(['periodo_rematricula_id' => $this->campanha->id, 'serie_id' => $this->oitavo->id, 'valor_total' => 15600]);

        $turmaOrigem = Turma::create(['nome' => '6º Ano A 2026', 'periodo_letivo_id' => $this->origem->id, 'serie_id' => $this->sexto->id]);
        $a = [];

        foreach (range(1, 10) as $n) {
            $risco = match ($n) {
                6 => 70,
                9 => 10,
                10 => 80,
                default => 0,
            };
            $matricula = Matricula::create(['pessoa_id' => Pessoa::create(['nome' => "Aluno {$n}"])->id, 'turma_id' => $turmaOrigem->id, 'situacao' => 'ativa', 'risco_evasao_score' => $risco]);
            Contrato::create(['matricula_id' => $matricula->id, 'valor_total' => 12000, 'data_aceite' => '2026-01-05']);
            $a[$n] = $matricula;
        }

        $rematricula = fn (int $n, string $status, ?Serie $serie) => Rematricula::create(['periodo_rematricula_id' => $this->campanha->id, 'matricula_origem_id' => $a[$n]->id, 'solicitante_user_id' => $this->solicitante->id, 'serie_destino_id' => $serie?->id, 'status' => $status]);

        foreach ([1, 2, 3] as $n) {
            $rematricula($n, 'confirmada', $this->setimo);
        }

        $rematricula(4, 'dados_confirmados', $this->setimo);
        $rematricula(5, 'dados_confirmados', $this->setimo);
        $rematricula(6, 'iniciada', $this->oitavo);
        $rematricula(7, 'cancelada', $this->setimo);
        $rematricula(8, 'iniciada', null);

        $turma7 = Turma::create(['nome' => '7º Ano A 2027', 'periodo_letivo_id' => $this->destino->id, 'serie_id' => $this->setimo->id, 'vagas_maximas' => 4, 'status' => 'planejada', 'custo_docente_mensal' => 3000, 'custo_operacional_rateado' => 600]);
        $turma8 = Turma::create(['nome' => '8º Ano A 2027', 'periodo_letivo_id' => $this->destino->id, 'serie_id' => $this->oitavo->id, 'vagas_maximas' => 30, 'status' => 'planejada']);

        foreach ([[$turma7, StatusListaEspera::Aguardando], [$turma7, StatusListaEspera::Aguardando], [$turma7, StatusListaEspera::Aguardando], [$turma7, StatusListaEspera::Desistiu], [$turma8, StatusListaEspera::Notificado]] as [$turma, $status]) {
            ListaEsperaMatricula::create(['turma_id' => $turma->id, 'periodo_letivo_id' => $this->destino->id, 'pessoa_id' => Pessoa::create(['nome' => 'Espera '.uniqid()])->id, 'status' => $status]);
        }
    }

    private function servico(): SimuladorProximoAnoService
    {
        return app(SimuladorProximoAnoService::class);
    }

    /**
     * @param  array<string, mixed>  $parametros
     * @return array<string, mixed>
     */
    private function projetar(array $parametros = []): array
    {
        return $this->servico()->projetar($this->campanha->fresh(), $parametros);
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @return array<string, mixed>|null
     */
    private function linha(array $resultado, Serie $serie): ?array
    {
        return collect($resultado['linhas'])->firstWhere('serie_id', $serie->id);
    }

    // --- Projeção ----------------------------------------------------------------------------------------------------

    public function test_projecao_do_setimo_ano_soma_confirmadas_e_ponderada_as_em_andamento(): void
    {
        $l = $this->linha($this->projetar(), $this->setimo);

        $this->assertSame(3, $l['confirmadas']);
        $this->assertSame(2, $l['em_andamento']);
        $this->assertEquals(1.8, $l['em_andamento_ponderado'], '2 x 90%');
        $this->assertEquals(4.8, $l['projecao']);
        $this->assertSame(4, $l['vagas']);
        $this->assertEquals(0.8, $l['excesso']);
        $this->assertSame('acima_da_capacidade', $l['situacao']);
        $this->assertEquals(0.0, $l['espera_incluida'], 'a turma já passou das vagas: ninguém da espera entra');
        $this->assertSame(3, $l['espera']);
        $this->assertTrue($l['espera_maior_que_vagas']);
    }

    public function test_quem_tem_risco_alto_sai_da_conta_e_a_espera_ocupa_as_vagas_que_sobram(): void
    {
        $l = $this->linha($this->projetar(), $this->oitavo);

        $this->assertSame(0, $l['em_andamento']);
        $this->assertSame(1, $l['em_risco']);
        $this->assertSame(1, $l['espera']);
        $this->assertEquals(1.0, $l['espera_incluida']);
        $this->assertEquals(1.0, $l['projecao']);
        $this->assertSame('ok', $l['situacao']);
        $this->assertFalse($l['espera_maior_que_vagas']);
    }

    public function test_serie_sem_turma_e_sem_demanda_nao_aparece(): void
    {
        $this->assertNull($this->linha($this->projetar(), $this->sexto));
    }

    public function test_quem_ainda_nao_tem_serie_de_destino_entra_so_no_total(): void
    {
        $r = $this->projetar();

        // a8 em andamento sem série (90%) + a9 que nem iniciou (60%); a10 tem risco 80 e sai.
        $this->assertSame(1, $r['sem_serie']['em_andamento']);
        $this->assertSame(2, $r['sem_serie']['nao_iniciadas']);
        $this->assertSame(1, $r['sem_serie']['nao_iniciadas_em_risco']);
        $this->assertEquals(1.5, $r['sem_serie']['projecao']);
        $this->assertEquals(7.3, $r['totais']['projecao'], '4,8 + 1 + 1,5');
    }

    public function test_totais_e_receita_contra_a_do_ano_em_curso(): void
    {
        $t = $this->projetar()['totais'];

        $this->assertSame(10, $t['elegiveis']);
        $this->assertSame(3, $t['confirmadas']);
        $this->assertSame(3, $t['em_andamento']);
        $this->assertSame(2, $t['nao_iniciadas']);
        $this->assertSame(1, $t['recusaram']);
        $this->assertSame(2, $t['turmas']);
        $this->assertSame(34, $t['vagas']);
        // 4,8 x 1.200 + 1 x 1.300 + 1,5 x 1.000 (média dos contratos atuais)
        $this->assertEquals(8560.0, $t['receita_mensal']);
        $this->assertEquals(10000.0, $t['receita_atual_mensal']);
        $this->assertEquals(-1440.0, $t['variacao_receita']);
        $this->assertSame(1, $t['series_com_problema']);
    }

    public function test_preco_vem_da_tabela_e_na_falta_dela_da_media_dos_contratos(): void
    {
        $this->campanha->precos()->where('serie_id', $this->oitavo->id)->delete();

        $r = $this->projetar();

        $this->assertSame('tabela', $this->linha($r, $this->setimo)['preco_origem']);
        $this->assertSame('media', $this->linha($r, $this->oitavo)['preco_origem']);
        $this->assertEquals(12000.0, $this->linha($r, $this->oitavo)['preco_anual']);
    }

    public function test_sem_alunos_nem_rematriculas_a_projecao_avisa_que_esta_vazia(): void
    {
        Rematricula::query()->delete();
        Matricula::query()->update(['situacao' => 'cancelada']);

        $r = $this->projetar();

        $this->assertEquals(0.0, $r['sem_serie']['projecao']);
        $this->assertContains('Não há alunos ativos no período de origem nem rematrículas na campanha: a projeção está vazia.', $r['avisos']);
    }

    // --- O "e se" ----------------------------------------------------------------------------------------------------

    public function test_abrir_mais_uma_turma_leva_a_espera_e_resolve_o_excesso(): void
    {
        $l = $this->linha($this->projetar(['turmas' => [$this->setimo->id => 2], 'capacidade' => [$this->setimo->id => 30]]), $this->setimo);

        $this->assertSame(2, $l['turmas']);
        $this->assertSame(60, $l['vagas']);
        $this->assertEquals(3.0, $l['espera_incluida']);
        $this->assertEquals(7.8, $l['projecao']);
        $this->assertEquals(3.9, $l['alunos_por_turma']);
        $this->assertSame('ok', $l['situacao'], '3,9 por turma passa do equilíbrio de 3');
    }

    public function test_turma_demais_fica_abaixo_do_ponto_de_equilibrio(): void
    {
        $l = $this->linha($this->projetar(['turmas' => [$this->setimo->id => 3], 'capacidade' => [$this->setimo->id => 30]]), $this->setimo);

        $this->assertSame(3, $l['ponto_equilibrio'], 'custo de R$ 3.600 por turma sobre R$ 1.200 por aluno');
        $this->assertEquals(2.6, $l['alunos_por_turma']);
        $this->assertSame('abaixo_do_equilibrio', $l['situacao']);
    }

    public function test_fechar_a_serie_deixa_a_demanda_sem_turma(): void
    {
        $l = $this->linha($this->projetar(['turmas' => [$this->setimo->id => 0]]), $this->setimo);

        $this->assertSame(0, $l['vagas']);
        $this->assertSame('sem_turma', $l['situacao']);
        $this->assertEquals(4.8, $l['excesso']);
        $this->assertNull($l['alunos_por_turma']);
    }

    public function test_abrir_turma_em_serie_que_nao_tinha_cria_a_linha(): void
    {
        $l = $this->linha($this->projetar(['turmas' => [$this->sexto->id => 1], 'capacidade' => [$this->sexto->id => 25]]), $this->sexto);

        $this->assertSame(25, $l['vagas']);
        $this->assertSame(1, $l['turmas']);
        $this->assertSame(0, $l['turmas_atuais']);
    }

    public function test_taxas_corte_de_risco_espera_e_reajuste_mudam_o_resultado(): void
    {
        $this->assertEquals(5.0, $this->linha($this->projetar(['taxa_andamento' => 100]), $this->setimo)['projecao']);

        // Corte 0 desliga o filtro de risco: a6 volta (90% de 1) e a10 também.
        $semCorte = $this->projetar(['corte_risco' => 0]);
        $this->assertEquals(0.9 + 1.0, $this->linha($semCorte, $this->oitavo)['projecao'], '0,9 de a6 e 1 da espera');
        $this->assertSame(0, $semCorte['sem_serie']['nao_iniciadas_em_risco']);

        $semEspera = $this->linha($this->projetar(['incluir_espera' => false]), $this->oitavo);
        $this->assertEquals(0.0, $semEspera['espera_incluida']);

        $reajustado = $this->linha($this->projetar(['reajuste_percentual' => 10]), $this->setimo);
        $this->assertEquals(15840.0, $reajustado['preco_anual']);
        $this->assertEquals(6336.0, $reajustado['receita_mensal'], '4,8 x 1.320');
    }

    public function test_parametros_invalidos_sao_corrigidos(): void
    {
        $p = $this->servico()->normalizar(['taxa_andamento' => 250, 'taxa_nao_iniciadas' => -5, 'corte_risco' => 500, 'reajuste_percentual' => 900, 'turmas' => ['7' => -2, '8' => '', '9' => '3'], 'capacidade' => ['7' => 0, '8' => null]]);

        $this->assertSame(100.0, $p['taxa_andamento']);
        $this->assertSame(0.0, $p['taxa_nao_iniciadas']);
        $this->assertSame(100, $p['corte_risco']);
        $this->assertSame(100.0, $p['reajuste_percentual']);
        $this->assertSame([7 => 0, 9 => 3], $p['turmas']);
        $this->assertSame([7 => 1], $p['capacidade']);
        $this->assertSame(90.0, $this->servico()->parametrosPadrao()['taxa_andamento']);
    }

    // --- Sugestões, cenários e alertas -------------------------------------------------------------------------------

    public function test_sugestoes_vem_da_campanha_anterior_encerrada(): void
    {
        $this->assertSame(['periodo' => null, 'taxa_andamento' => null, 'taxa_renovacao' => null], $this->servico()->sugestoes($this->campanha));

        $antigoOrigem = PeriodoLetivo::create(['nome' => '2025', 'ano' => 2025, 'data_inicio' => '2025-02-01', 'data_fim' => '2025-12-15']);
        $anterior = PeriodoRematricula::create(['nome' => 'Rematrícula 2026', 'periodo_letivo_origem_id' => $antigoOrigem->id, 'periodo_letivo_destino_id' => $this->origem->id, 'data_inicio' => '2025-10-01', 'data_fim' => '2025-11-15', 'is_ativo' => false]);
        $turma = Turma::create(['nome' => '6º Ano 2025', 'periodo_letivo_id' => $antigoOrigem->id, 'serie_id' => $this->sexto->id]);

        $matriculas = collect(range(1, 8))->map(fn (int $n): Matricula => Matricula::create(['pessoa_id' => Pessoa::create(['nome' => "Antigo {$n}"])->id, 'turma_id' => $turma->id, 'situacao' => 'ativa']));

        foreach (range(0, 3) as $i) {
            Rematricula::create(['periodo_rematricula_id' => $anterior->id, 'matricula_origem_id' => $matriculas[$i]->id, 'solicitante_user_id' => $this->solicitante->id, 'status' => 'confirmada']);
        }

        Rematricula::create(['periodo_rematricula_id' => $anterior->id, 'matricula_origem_id' => $matriculas[4]->id, 'solicitante_user_id' => $this->solicitante->id, 'status' => 'iniciada']);

        $s = $this->servico()->sugestoes($this->campanha);

        $this->assertSame('Rematrícula 2026', $s['periodo']);
        $this->assertEquals(80.0, $s['taxa_andamento'], '4 confirmadas contra 1 que ficou pelo caminho');
        $this->assertEquals(50.0, $s['taxa_renovacao'], '4 de 8 alunos elegíveis');
    }

    public function test_salvar_guarda_os_parametros_e_a_fotografia_do_resultado(): void
    {
        $cenario = $this->servico()->salvar($this->campanha, '  Conservador ', ['taxa_andamento' => 70], null);

        $this->assertSame('Conservador', $cenario->nome);
        $this->assertEquals(70.0, $cenario->parametros['taxa_andamento']);
        $this->assertEquals(70.0, $cenario->resultado['parametros']['taxa_andamento']);
        $this->assertArrayHasKey('linhas', $cenario->resultado);
        $this->assertEqualsWithDelta($this->projetar(['taxa_andamento' => 70])['totais']['projecao'], $cenario->resultado['totais']['projecao'], 0.001);
    }

    public function test_salvar_sem_nome_e_recusado(): void
    {
        $this->expectException(\DomainException::class);

        $this->servico()->salvar($this->campanha, '   ', []);
    }

    public function test_comparar_poe_os_cenarios_lado_a_lado(): void
    {
        $this->servico()->salvar($this->campanha, 'Conservador', ['taxa_andamento' => 50, 'taxa_nao_iniciadas' => 30]);
        $this->servico()->salvar($this->campanha, 'Otimista', ['taxa_andamento' => 100, 'taxa_nao_iniciadas' => 90, 'reajuste_percentual' => 5]);

        $linhas = collect($this->servico()->comparar(CenarioProximoAno::query()->orderBy('id')->get()))->keyBy('nome');

        $this->assertGreaterThan($linhas['Conservador']['projecao'], $linhas['Otimista']['projecao']);
        $this->assertGreaterThan($linhas['Conservador']['receita_mensal'], $linhas['Otimista']['receita_mensal']);
        $this->assertEquals(50.0, $linhas['Conservador']['taxa_andamento']);
        $this->assertEquals(5.0, $linhas['Otimista']['reajuste_percentual']);
    }

    public function test_alertas_apontam_o_problema_de_cada_serie_e_a_urgencia_vem_do_fim_da_campanha(): void
    {
        $alertas = $this->servico()->alertas($this->campanha);

        $this->assertCount(1, $alertas);
        $this->assertSame('Ensino Fundamental — 7º Ano', $alertas[0]['serie']);
        $this->assertSame('Acima da capacidade', $alertas[0]['problema']);
        $this->assertFalse($alertas[0]['urgente'], 'faltam 20 dias para 15/11');

        $this->travel(8)->days();
        $this->assertTrue($this->servico()->alertas($this->campanha)[0]['urgente'], 'faltam 12 dias');
    }

    public function test_alerta_de_espera_maior_que_as_vagas_que_sobram(): void
    {
        // Quatro na espera do 8º ano e só uma vaga livre depois do que já está confirmado.
        $this->oitavo->turmas()->update(['vagas_maximas' => 1]);
        $turma = Turma::query()->where('serie_id', $this->oitavo->id)->firstOrFail();

        foreach (range(1, 3) as $n) {
            ListaEsperaMatricula::create(['turma_id' => $turma->id, 'periodo_letivo_id' => $this->destino->id, 'pessoa_id' => Pessoa::create(['nome' => "Extra {$n}"])->id, 'status' => StatusListaEspera::Aguardando]);
        }

        $problemas = collect($this->servico()->alertas($this->campanha))->keyBy('serie');

        $this->assertSame('Lista de espera maior que as vagas', $problemas['Ensino Fundamental — 8º Ano']['problema']);
    }

    public function test_periodo_padrao_prefere_a_campanha_aberta(): void
    {
        PeriodoRematricula::create(['nome' => 'Campanha mais nova, fechada', 'periodo_letivo_origem_id' => $this->destino->id, 'periodo_letivo_destino_id' => $this->destino->id, 'data_inicio' => '2026-12-01', 'data_fim' => '2026-12-20', 'is_ativo' => false]);

        $this->assertSame($this->campanha->id, $this->servico()->periodoPadrao()->id);
    }
}
