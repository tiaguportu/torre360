<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\IndicadorDoPainel;
use App\Filament\Pages\RelatorioInadimplencia;
use App\Models\Banco;
use App\Models\ContaRecorrente;
use App\Models\Contrato;
use App\Models\Curso;
use App\Models\Fatura;
use App\Models\IndicadorMeta;
use App\Models\Interessado;
use App\Models\ItemFatura;
use App\Models\Matricula;
use App\Models\OrigemInteressado;
use App\Models\PainelSnapshot;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\PlanoConta;
use App\Models\Serie;
use App\Models\StatusInteressado;
use App\Models\TransacaoBancaria;
use App\Models\Turma;
use App\Models\Unidade;
use App\Models\User;
use App\Services\DREService;
use App\Services\Painel\Indicadores\AlunosEmRiscoDeEvasao;
use App\Services\Painel\Indicadores\ConversaoDeLeads90Dias;
use App\Services\Painel\Indicadores\InadimplenciaEmAberto;
use App\Services\Painel\Indicadores\LeadsNovos30Dias;
use App\Services\Painel\Indicadores\MatriculasAtivas;
use App\Services\Painel\Indicadores\OcupacaoDasTurmas;
use App\Services\Painel\Indicadores\ResultadoDoMesAnterior;
use App\Services\Painel\Indicadores\TaxaDeInadimplencia;
use App\Services\Painel\Indicadores\VagasLivres;
use App\Services\Painel\PainelDirecaoService;
use App\Support\Painel\ContextoPainel;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

/**
 * Painel da Direção (Onda 45), regras: cada indicador com a sua definição, permissão por indicador, comparação com a fotografia de 30
 * dias, meta e semáforo.
 *
 * "Hoje" é segunda-feira, 26/10/2026.
 *
 * Cenário: unidade 1 com a turma T1 (10 vagas, 3 alunos ativos); unidade 2 com a T2 (2 vagas, 3 alunos ativos: acima do limite).
 */
class PainelDirecaoTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    private Unidade $unidade1;

    private Unidade $unidade2;

    private Turma $t1;

    private Turma $t2;

    private ContextoPainel $escola;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-26 09:00:00'));
        Cache::flush();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->escola = new ContextoPainel(CarbonImmutable::today());

        $this->unidade1 = Unidade::create(['nome' => 'Unidade Central', 'situacao_funcionamento' => '1']);
        $this->unidade2 = Unidade::create(['nome' => 'Unidade Norte', 'situacao_funcionamento' => '1']);
        $periodo = PeriodoLetivo::create(['nome' => '2026', 'ano' => 2026, 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);

        $this->t1 = $this->turma($this->unidade1, 'T1', 10, $periodo);
        $this->t2 = $this->turma($this->unidade2, 'T2', 2, $periodo);

        $this->aluno($this->t1, 'A1', ['risco_evasao_score' => 70]);
        $this->aluno($this->t1, 'A2', ['risco_evasao_score' => 60]);
        $this->aluno($this->t1, 'A3', ['risco_evasao_score' => 59]);
        $this->aluno($this->t1, 'A4 trancado', ['situacao' => 'trancada', 'risco_evasao_score' => 90]);
        $this->aluno($this->t1, 'A5 saiu ontem', ['data_desativacao' => '2026-10-25', 'risco_evasao_score' => 90]);
        $this->aluno($this->t2, 'B1', ['risco_evasao_score' => 80]);
        $this->aluno($this->t2, 'B2');
        $this->aluno($this->t2, 'B3');
    }

    private function turma(Unidade $unidade, string $nome, int $vagas, PeriodoLetivo $periodo): Turma
    {
        $curso = Curso::create(['unidade_id' => $unidade->id, 'nome_externo' => "Curso {$nome}", 'nome_interno' => $nome]);

        return Turma::create(['nome' => $nome, 'periodo_letivo_id' => $periodo->id, 'vagas_maximas' => $vagas, 'serie_id' => Serie::create(['curso_id' => $curso->id, 'nome' => "Série {$nome}", 'sistema_avaliacao' => 'Nota'])->id]);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function aluno(Turma $turma, string $nome, array $atributos = []): Matricula
    {
        return Matricula::create($atributos + ['pessoa_id' => Pessoa::create(['nome' => $nome])->id, 'turma_id' => $turma->id, 'situacao' => 'ativa']);
    }

    private function servico(): PainelDirecaoService
    {
        return app(PainelDirecaoService::class);
    }

    private function usuario(string $papel, array $permissoes): User
    {
        Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);

        foreach ($permissoes as $nome) {
            Role::findByName($papel)->givePermissionTo(Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']));
        }

        $usuario = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $usuario->assignRole($papel);
        session(['active_role' => $papel]);

        return $usuario;
    }

    private function fatura(string $vencimento, float $valor, string $status = 'pendente', float $pago = 0.0): Fatura
    {
        $matricula = Matricula::query()->firstOrFail();
        $contrato = Contrato::firstOrCreate(['matricula_id' => $matricula->id], ['valor_total' => 12000, 'data_aceite' => '2026-01-05']);
        $fatura = Fatura::create(['contrato_id' => $contrato->id, 'vencimento' => $vencimento, 'status' => $status]);
        ItemFatura::create(['fatura_id' => $fatura->id, 'descricao' => 'Mensalidade', 'quantidade' => 1, 'valor_unitario' => $valor, 'desconto' => 0, 'tipo_desconto' => 'absoluto']);

        if ($pago > 0) {
            $banco = Banco::first() ?? Banco::create(['nome' => 'Banco Teste', 'is_active' => true]);
            TransacaoBancaria::create(['banco_id' => $banco->id, 'fatura_id' => $fatura->id, 'tipo' => 'entrada', 'valor' => $pago, 'data_transacao' => $vencimento, 'descricao' => 'Pagamento', 'conciliado' => true]);
        }

        return $fatura;
    }

    // --- Indicadores -------------------------------------------------------------------------------------------------

    public function test_matriculas_ativas_respeitam_a_situacao_e_as_datas_e_o_filtro_de_unidade(): void
    {
        $indicador = new MatriculasAtivas;

        $this->assertEquals(6.0, $indicador->valor($this->escola));
        $this->assertEquals(3.0, $indicador->valor(new ContextoPainel(CarbonImmutable::today(), $this->unidade1->id)));
        $this->assertEquals(3.0, $indicador->valor(new ContextoPainel(CarbonImmutable::today(), $this->unidade2->id)));
    }

    public function test_ocupacao_e_vagas_livres_nao_deixam_a_turma_lotada_emprestar_vaga(): void
    {
        // Escola: 12 vagas, 6 alunos. T1 tem 7 livres; T2 está acima do limite e não conta negativo.
        $this->assertEquals(50.0, (new OcupacaoDasTurmas)->valor($this->escola));
        $this->assertEquals(7.0, (new VagasLivres)->valor($this->escola));
        $this->assertEquals(30.0, (new OcupacaoDasTurmas)->valor(new ContextoPainel(CarbonImmutable::today(), $this->unidade1->id)));
        $this->assertEquals(150.0, (new OcupacaoDasTurmas)->valor(new ContextoPainel(CarbonImmutable::today(), $this->unidade2->id)));
        $this->assertEquals(0.0, (new VagasLivres)->valor(new ContextoPainel(CarbonImmutable::today(), $this->unidade2->id)));
    }

    public function test_sem_turmas_nao_ha_ocupacao_para_calcular(): void
    {
        Turma::query()->update(['status' => 'concluida']);

        $this->assertNull((new OcupacaoDasTurmas)->valor($this->escola));
        $this->assertNull((new VagasLivres)->valor($this->escola));
    }

    public function test_risco_alto_de_evasao_usa_o_corte_da_configuracao(): void
    {
        $this->assertEquals(3.0, (new AlunosEmRiscoDeEvasao)->valor($this->escola), 'A1 (70), A2 (60) e B1 (80); o trancado e o desativado ficam de fora');
        $this->assertEquals(2.0, (new AlunosEmRiscoDeEvasao)->valor(new ContextoPainel(CarbonImmutable::today(), $this->unidade1->id)));

        config(['risco_evasao.faixas_cor.alto' => 75]);
        $this->assertEquals(1.0, (new AlunosEmRiscoDeEvasao)->valor($this->escola));
    }

    public function test_inadimplencia_em_aberto_e_o_mesmo_total_devido_do_relatorio(): void
    {
        $this->fatura('2026-09-10', 1000, 'atrasado');
        $this->fatura('2026-10-05', 800, 'parcial', 300);
        $this->fatura('2026-11-05', 500);
        $this->fatura('2026-09-20', 700, 'pago', 700);

        $indicador = (new InadimplenciaEmAberto)->valor($this->escola);
        $relatorio = (new RelatorioInadimplencia)->getResumo()['total_devido'];

        $this->assertEquals(1500.0, $indicador, '1.000 + 500 de saldo da parcial');
        $this->assertEquals(round((float) $relatorio, 2), $indicador, 'o painel e o relatório não podem divergir');
    }

    public function test_taxa_de_inadimplencia_e_o_aberto_sobre_o_que_venceu_em_90_dias(): void
    {
        $this->fatura('2026-09-10', 1000, 'pago', 1000);
        $this->fatura('2026-10-01', 1000, 'atrasado');
        $this->fatura('2026-10-02', 1000, 'cancelado');
        $this->fatura('2026-11-01', 1000);
        $this->fatura('2026-06-01', 1000, 'atrasado'); // fora da janela de 90 dias

        $this->assertEquals(50.0, (new TaxaDeInadimplencia)->valor($this->escola));
    }

    public function test_taxa_de_inadimplencia_sem_faturas_nao_tem_valor(): void
    {
        $this->assertNull((new TaxaDeInadimplencia)->valor($this->escola));
    }

    public function test_resultado_do_mes_anterior_e_o_do_dre(): void
    {
        $banco = Banco::create(['nome' => 'Banco Teste', 'is_active' => true]);
        $receita = PlanoConta::create(['codigo' => '1', 'nome' => 'Receitas', 'tipo' => 'receita']);
        $despesa = PlanoConta::create(['codigo' => '2', 'nome' => 'Despesas', 'tipo' => 'despesa']);
        TransacaoBancaria::create(['banco_id' => $banco->id, 'plano_conta_id' => $receita->id, 'tipo' => 'entrada', 'valor' => 5000, 'data_transacao' => '2026-09-10', 'descricao' => 'R', 'conciliado' => true]);
        TransacaoBancaria::create(['banco_id' => $banco->id, 'plano_conta_id' => $despesa->id, 'tipo' => 'saida', 'valor' => 3200, 'data_transacao' => '2026-09-20', 'descricao' => 'D', 'conciliado' => true]);
        TransacaoBancaria::create(['banco_id' => $banco->id, 'plano_conta_id' => $despesa->id, 'tipo' => 'saida', 'valor' => 900, 'data_transacao' => '2026-10-02', 'descricao' => 'Outro mês', 'conciliado' => true]);

        $indicador = (new ResultadoDoMesAnterior)->valor($this->escola);
        $dre = app(DREService::class)->generate('2026-09-01', '2026-09-30')['resumo']['resultado_liquido'];

        $this->assertEquals(1800.0, $indicador);
        $this->assertEquals((float) $dre, $indicador);
    }

    public function test_leads_novos_e_conversao(): void
    {
        $ativo = StatusInteressado::create(['nome' => 'Em Atendimento', 'cor' => 'warning', 'ordem' => 1, 'is_final' => false, 'is_ganho' => false]);
        $ganho = StatusInteressado::create(['nome' => 'Matriculado', 'cor' => 'success', 'ordem' => 2, 'is_final' => true, 'is_ganho' => true]);
        $origem = OrigemInteressado::create(['nome' => 'Site']);

        $lead = function (StatusInteressado $status, int $diasAtras) use ($origem): void {
            $i = Interessado::create(['pessoa_id' => Pessoa::factory()->create()->id, 'status_interessado_id' => $status->id, 'origem_interessado_id' => $origem->id]);
            $i->forceFill(['created_at' => now()->subDays($diasAtras)])->saveQuietly();
        };

        $lead($ativo, 5);
        $lead($ativo, 20);
        $lead($ganho, 25);
        $lead($ganho, 60);
        $lead($ativo, 80);
        $lead($ganho, 120); // fora das duas janelas

        $this->assertEquals(3.0, (new LeadsNovos30Dias)->valor($this->escola));
        $this->assertEquals(40.0, (new ConversaoDeLeads90Dias)->valor($this->escola), '2 de 5 nos últimos 90 dias');
    }

    public function test_conversao_sem_leads_nao_tem_valor(): void
    {
        $this->assertNull((new ConversaoDeLeads90Dias)->valor($this->escola));
    }

    // --- Permissão por indicador -------------------------------------------------------------------------------------

    public function test_cada_pessoa_ve_so_os_indicadores_da_sua_permissao(): void
    {
        $pedagogico = $this->usuario('coordenacao', ['ViewAny:Matricula', 'ViewAny:Turma']);
        $chaves = array_map(fn (IndicadorDoPainel $i): string => $i->chave(), $this->servico()->visiveisPara($pedagogico));

        $this->assertSame(['matriculas_ativas', 'ocupacao_das_turmas', 'vagas_livres', 'alunos_em_risco_de_evasao'], $chaves);

        $financeiro = $this->usuario('financas', ['View:RelatorioInadimplencia', 'View:FluxoProjetado']);
        $chaves = array_map(fn (IndicadorDoPainel $i): string => $i->chave(), $this->servico()->visiveisPara($financeiro));

        $this->assertSame(['inadimplencia_em_aberto', 'taxa_de_inadimplencia', 'caixa_projetado_30_dias'], $chaves);

        $this->assertSame([], $this->servico()->visiveisPara($this->usuario('sem_nada', [])));
    }

    public function test_cartoes_agrupam_por_secao_na_ordem_da_configuracao(): void
    {
        $usuario = $this->usuario('direcao', ['ViewAny:Matricula', 'ViewAny:Turma', 'View:RelatorioInadimplencia']);

        $grupos = $this->servico()->cartoes($usuario);

        $this->assertSame(['Alunos e vagas', 'Financeiro'], array_keys($grupos));
        $this->assertSame('6', $grupos['Alunos e vagas'][0]['valor_formatado']);
        $this->assertSame('50,0%', $grupos['Alunos e vagas'][1]['valor_formatado']);
    }

    public function test_filtrar_unidade_marca_o_indicador_que_so_existe_para_a_escola_toda(): void
    {
        $usuario = $this->usuario('direcao', ['ViewAny:Matricula', 'View:RelatorioInadimplencia']);

        $grupos = $this->servico()->cartoes($usuario, $this->unidade1->id);

        $this->assertSame('3', $grupos['Alunos e vagas'][0]['valor_formatado']);
        $this->assertFalse($grupos['Alunos e vagas'][0]['escola_toda']);
        $this->assertTrue($grupos['Financeiro'][0]['escola_toda']);
    }

    // --- Semáforo, variação e formato --------------------------------------------------------------------------------

    private function meta(string $indicador, float $meta, float $tolerancia = 10, int $unidade = 0): IndicadorMeta
    {
        return IndicadorMeta::create(['indicador' => $indicador, 'ano' => 2026, 'unidade_id' => $unidade, 'meta' => $meta, 'tolerancia_percentual' => $tolerancia]);
    }

    public function test_semaforo_quando_maior_e_melhor(): void
    {
        $indicador = new MatriculasAtivas;
        $meta = $this->meta('matriculas_ativas', 100, 10);

        $this->assertSame('verde', $this->servico()->semaforo($indicador, 100, $meta));
        $this->assertSame('verde', $this->servico()->semaforo($indicador, 120, $meta));
        $this->assertSame('amarelo', $this->servico()->semaforo($indicador, 90, $meta));
        $this->assertSame('vermelho', $this->servico()->semaforo($indicador, 89.9, $meta));
        $this->assertNull($this->servico()->semaforo($indicador, null, $meta));
        $this->assertNull($this->servico()->semaforo($indicador, 100, null));
    }

    public function test_semaforo_quando_menor_e_melhor(): void
    {
        $indicador = new TaxaDeInadimplencia;
        $meta = $this->meta('taxa_de_inadimplencia', 5, 20);

        $this->assertSame('verde', $this->servico()->semaforo($indicador, 4, $meta));
        $this->assertSame('verde', $this->servico()->semaforo($indicador, 5, $meta));
        $this->assertSame('amarelo', $this->servico()->semaforo($indicador, 6, $meta));
        $this->assertSame('vermelho', $this->servico()->semaforo($indicador, 6.1, $meta));
    }

    public function test_variacao_diz_se_a_mudanca_e_boa_ou_ruim_para_o_indicador(): void
    {
        $matriculas = new MatriculasAtivas;
        $inadimplencia = new InadimplenciaEmAberto;

        $this->assertSame('melhor', $this->servico()->variacao($matriculas, 110, 100)['sentido']);
        $this->assertSame('pior', $this->servico()->variacao($matriculas, 90, 100)['sentido']);
        $this->assertSame('pior', $this->servico()->variacao($inadimplencia, 1100, 1000)['sentido']);
        $this->assertSame('melhor', $this->servico()->variacao($inadimplencia, 900, 1000)['sentido']);
        $this->assertSame('igual', $this->servico()->variacao($matriculas, 100, 100)['sentido']);
        $this->assertEquals(10.0, $this->servico()->variacao($matriculas, 110, 100)['percentual']);
        $this->assertNull($this->servico()->variacao($matriculas, 100, 0)['percentual'], 'sem base para o percentual');
        $this->assertNull($this->servico()->variacao($matriculas, null, 100));
        $this->assertNull($this->servico()->variacao($matriculas, 100, null));
    }

    public function test_formatos(): void
    {
        $this->assertSame('1.234', $this->servico()->formatar('inteiro', 1234.0));
        $this->assertSame('R$ 1.234,50', $this->servico()->formatar('dinheiro', 1234.5));
        $this->assertSame('R$ -50,00', $this->servico()->formatar('dinheiro', -50.0));
        $this->assertSame('12,5%', $this->servico()->formatar('percentual', 12.5));
        $this->assertSame('—', $this->servico()->formatar('inteiro', null));
    }

    public function test_meta_da_unidade_vence_a_da_escola_toda_que_serve_de_reserva(): void
    {
        $escolaToda = $this->meta('matriculas_ativas', 100);
        $daUnidade = $this->meta('matriculas_ativas', 40, 10, $this->unidade1->id);

        $this->assertSame($daUnidade->id, $this->servico()->meta('matriculas_ativas', 2026, $this->unidade1->id)->id);
        $this->assertSame($escolaToda->id, $this->servico()->meta('matriculas_ativas', 2026, $this->unidade2->id)->id);
        $this->assertSame($escolaToda->id, $this->servico()->meta('matriculas_ativas', 2026, 0)->id);
        $this->assertNull($this->servico()->meta('matriculas_ativas', 2025, 0));
    }

    // --- Fotografias e comparação ------------------------------------------------------------------------------------

    public function test_fotografar_grava_a_escola_toda_e_cada_unidade_so_nos_indicadores_que_suportam(): void
    {
        config(['painel_direcao.indicadores' => [MatriculasAtivas::class, TaxaDeInadimplencia::class]]);

        $gravadas = $this->servico()->fotografar();

        // Matrículas: escola + 2 unidades; taxa de inadimplência: só a escola.
        $this->assertSame(4, $gravadas);
        $this->assertEquals(6.0, PainelSnapshot::query()->where('indicador', 'matriculas_ativas')->where('unidade_id', 0)->value('valor'));
        $this->assertEquals(3.0, PainelSnapshot::query()->where('indicador', 'matriculas_ativas')->where('unidade_id', $this->unidade1->id)->value('valor'));
        $this->assertNull(PainelSnapshot::query()->where('indicador', 'taxa_de_inadimplencia')->value('valor'));
        $this->assertSame(1, PainelSnapshot::query()->where('indicador', 'taxa_de_inadimplencia')->count());
    }

    public function test_fotografar_de_novo_no_mesmo_dia_atualiza_em_vez_de_duplicar(): void
    {
        config(['painel_direcao.indicadores' => [MatriculasAtivas::class]]);

        $this->servico()->fotografar();
        $this->aluno($this->t1, 'A6');
        $this->servico()->esquecer();
        $this->servico()->fotografar();

        $this->assertSame(3, PainelSnapshot::query()->count());
        $this->assertEquals(7.0, PainelSnapshot::query()->where('unidade_id', 0)->value('valor'));
    }

    public function test_comando_fotografa_e_limpa_as_antigas(): void
    {
        config(['painel_direcao.indicadores' => [MatriculasAtivas::class]]);
        PainelSnapshot::create(['data' => '2023-01-10', 'indicador' => 'matriculas_ativas', 'unidade_id' => 0, 'valor' => 50]);

        $this->artisan('painel:fotografar')->expectsOutputToContain('3 fotografia(s) gravada(s); 1 antiga(s) apagada(s).')->assertSuccessful();

        $this->assertSame(3, PainelSnapshot::query()->count());
    }

    public function test_cartao_compara_com_a_fotografia_de_30_dias_atras_e_com_a_do_ano_anterior(): void
    {
        config(['painel_direcao.indicadores' => [MatriculasAtivas::class]]);
        PainelSnapshot::create(['data' => '2026-09-26', 'indicador' => 'matriculas_ativas', 'unidade_id' => 0, 'valor' => 5]);
        PainelSnapshot::create(['data' => '2025-10-20', 'indicador' => 'matriculas_ativas', 'unidade_id' => 0, 'valor' => 8]);
        PainelSnapshot::create(['data' => '2026-10-10', 'indicador' => 'matriculas_ativas', 'unidade_id' => 0, 'valor' => 5.5]);

        $c = $this->servico()->cartoes($this->usuario('direcao', ['ViewAny:Matricula']))['Alunos e vagas'][0];

        $this->assertEquals(5.0, $c['anterior']['valor'], 'a mais recente que não seja posterior a 26/09');
        $this->assertSame('2026-09-26', $c['anterior']['data']->toDateString());
        $this->assertSame('melhor', $c['variacao']['sentido']);
        $this->assertEquals(20.0, $c['variacao']['percentual']);
        $this->assertSame('pior', $c['variacao_ano']['sentido'], '6 contra 8 um ano atrás');
        $this->assertSame([5.0, 5.5], $c['serie'], 'a série tem só as fotografias dos últimos 90 dias');
    }

    public function test_sem_fotografia_para_comparar_o_cartao_nao_inventa_variacao(): void
    {
        config(['painel_direcao.indicadores' => [MatriculasAtivas::class]]);
        PainelSnapshot::create(['data' => '2026-06-01', 'indicador' => 'matriculas_ativas', 'unidade_id' => 0, 'valor' => 3]);

        $c = $this->servico()->cartoes($this->usuario('direcao', ['ViewAny:Matricula']))['Alunos e vagas'][0];

        $this->assertNull($c['anterior'], 'a fotografia de junho está fora da janela de 60 dias');
        $this->assertNull($c['variacao']);
        $this->assertNull($c['variacao_ano']);
    }

    public function test_cartao_traz_a_meta_e_a_cor(): void
    {
        config(['painel_direcao.indicadores' => [MatriculasAtivas::class]]);
        $this->meta('matriculas_ativas', 8, 10);

        $c = $this->servico()->cartoes($this->usuario('direcao', ['ViewAny:Matricula']))['Alunos e vagas'][0];

        $this->assertSame('8', $c['meta_formatada']);
        $this->assertSame('vermelho', $c['semaforo'], '6 contra a meta 8 com 10% de tolerância (7,2)');
    }

    // --- Robustez ----------------------------------------------------------------------------------------------------

    public function test_indicador_que_falha_vira_sem_dado_e_nao_derruba_o_painel_nem_a_fotografia(): void
    {
        config(['painel_direcao.indicadores' => [IndicadorQueFalha::class, MatriculasAtivas::class]]);
        $usuario = $this->usuario('direcao', ['ViewAny:Matricula', 'ViewAny:Turma']);

        $grupos = $this->servico()->cartoes($usuario);

        $this->assertSame('—', $grupos['Teste'][0]['valor_formatado']);
        $this->assertSame('6', $grupos['Alunos e vagas'][0]['valor_formatado']);

        $this->assertSame(3, $this->servico()->fotografar(), 'só o indicador que funciona deixa fotografia');
        $this->assertSame(0, PainelSnapshot::query()->where('indicador', 'falha')->count());
    }

    public function test_valor_fica_em_cache_ate_o_botao_atualizar(): void
    {
        $indicador = new MatriculasAtivas;
        $this->assertEquals(6.0, $this->servico()->valor($indicador, $this->escola));

        $this->aluno($this->t1, 'A6');
        $this->assertEquals(6.0, $this->servico()->valor($indicador, $this->escola), 'ainda em cache');

        $this->servico()->esquecer();
        $this->assertEquals(7.0, $this->servico()->valor($indicador, $this->escola));
    }

    public function test_atencao_hoje_lista_as_filas_da_mesa_com_atraso(): void
    {
        $usuario = $this->usuario('financas', ['Update:ContaRecorrente', 'ViewAny:Matricula']);
        ContaRecorrente::create(['descricao' => 'Aluguel', 'periodicidade' => 'mensal', 'dia_vencimento' => 5, 'valor' => 4000, 'vigencia_inicio' => '2026-01-01', 'proximo_reajuste_em' => '2026-10-10']);

        $fila = $this->servico()->atencaoHoje($usuario);

        $this->assertCount(1, $fila);
        $this->assertSame('Reajustes de contrato', $fila[0]->fonte->titulo());
        $this->assertSame(1, $fila[0]->atrasados);
    }
}

/**
 * Indicador de teste que sempre falha, para provar que um indicador quebrado não derruba o painel.
 */
class IndicadorQueFalha implements IndicadorDoPainel
{
    public function chave(): string
    {
        return 'falha';
    }

    public function titulo(): string
    {
        return 'Indicador quebrado';
    }

    public function grupo(): string
    {
        return 'Teste';
    }

    public function formato(): string
    {
        return 'inteiro';
    }

    public function melhor(): string
    {
        return 'maior';
    }

    public function permissao(): string
    {
        return 'ViewAny:Matricula';
    }

    public function descricao(): string
    {
        return 'Sempre falha.';
    }

    public function suportaUnidade(): bool
    {
        return false;
    }

    public function url(): ?string
    {
        return null;
    }

    public function valor(ContextoPainel $contexto): ?float
    {
        throw new \RuntimeException('Fonte indisponível.');
    }
}
