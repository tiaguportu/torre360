<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\Matricula;
use App\Models\OrigemInteressado;
use App\Models\PeriodoLetivo;
use App\Models\Serie;
use App\Models\StatusInteressado;
use App\Models\Turma;
use App\Models\Unidade;
use App\Models\User;
use App\Services\TermometroVagasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TermometroVagasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TermometroVagasService::limparCache();
    }

    private function criarSerie(string $nome = '1º Ano'): Serie
    {
        $unidade = Unidade::create(['nome' => 'Unidade Teste']);
        $curso = Curso::create([
            'unidade_id' => $unidade->id,
            'nome_externo' => 'Ensino Fundamental',
            'nome_interno' => 'EF',
        ]);

        return Serie::create([
            'nome' => $nome,
            'curso_id' => $curso->id,
            'sistema_avaliacao' => 'Nota',
        ]);
    }

    public function test_calcular_vagas_por_serie_com_turmas_e_matriculas(): void
    {
        $serie = $this->criarSerie('3º Ano');

        // Turma A: 20 vagas, 18 matrículas ativas -> 2 vagas restantes
        $turmaA = Turma::factory()->create([
            'serie_id' => $serie->id,
            'nome' => 'Turma 3A',
            'vagas_maximas' => 20,
        ]);

        for ($i = 0; $i < 18; $i++) {
            Matricula::factory()->create([
                'turma_id' => $turmaA->id,
                'situacao' => 'ativa',
                'data_desativacao' => null,
            ]);
        }

        // Turma B: 10 vagas, 5 matrículas ativas -> 5 vagas restantes
        $turmaB = Turma::factory()->create([
            'serie_id' => $serie->id,
            'nome' => 'Turma 3B',
            'vagas_maximas' => 10,
        ]);

        for ($i = 0; $i < 5; $i++) {
            Matricula::factory()->create([
                'turma_id' => $turmaB->id,
                'situacao' => 'ativa',
                'data_desativacao' => null,
            ]);
        }

        $dados = TermometroVagasService::calcularVagasPorSerie($serie->id);

        $this->assertSame($serie->id, $dados['serie_id']);
        $this->assertSame('3º Ano', $dados['serie_nome']);
        $this->assertSame(30, $dados['capacidade_total']);
        $this->assertSame(23, $dados['matriculas_ocupadas']);
        $this->assertSame(7, $dados['vagas_restantes']);
        $this->assertEquals(76.7, $dados['taxa_ocupacao']);
        // 76.7% de ocupação cai na faixa alerta (>= 75%)
        $this->assertSame(TermometroVagasService::STATUS_ALERTA, $dados['escassez']['status']);
        $this->assertCount(2, $dados['turmas_detalhes']);
    }

    public function test_classificacao_escassez_multinivel(): void
    {
        // 1. Esgotado: 0 vagas restantes
        $esgotado = TermometroVagasService::classificarEscassez(0, 100.0);
        $this->assertSame(TermometroVagasService::STATUS_ESGOTADO, $esgotado['status']);
        $this->assertSame('danger', $esgotado['cor']);
        $this->assertStringContainsString('Esgotado', $esgotado['label']);

        // 2. Crítico: 2 vagas restantes de 20
        $critico = TermometroVagasService::classificarEscassez(2, 90.0);
        $this->assertSame(TermometroVagasService::STATUS_CRITICO, $critico['status']);
        $this->assertSame('danger', $critico['cor']);
        $this->assertStringContainsString('Últimas', $critico['label']);

        // 3. Alerta: 5 vagas restantes de 30 (ocupação 83.3% ou <= 6 vagas)
        $alerta = TermometroVagasService::classificarEscassez(5, 83.3);
        $this->assertSame(TermometroVagasService::STATUS_ALERTA, $alerta['status']);
        $this->assertSame('warning', $alerta['cor']);
        $this->assertStringContainsString('Vagas Limitadas', $alerta['label']);

        // 4. Disponível: 15 vagas restantes de 30 (ocupação 50%)
        $disponivel = TermometroVagasService::classificarEscassez(15, 50.0);
        $this->assertSame(TermometroVagasService::STATUS_DISPONIVEL, $disponivel['status']);
        $this->assertSame('success', $disponivel['cor']);
        $this->assertStringContainsString('Disponível', $disponivel['label']);
    }

    public function test_obter_status_para_lead(): void
    {
        $serie = $this->criarSerie('1º Ano');
        $turma = Turma::factory()->create([
            'serie_id' => $serie->id,
            'vagas_maximas' => 10,
        ]);

        // 9 matrículas ocupadas de 10 -> 1 vaga restante (crítico)
        for ($i = 0; $i < 9; $i++) {
            Matricula::factory()->create([
                'turma_id' => $turma->id,
                'situacao' => 'ativa',
            ]);
        }

        $lead = Interessado::factory()->create();
        InteressadoDependente::create([
            'interessado_id' => $lead->id,
            'nome_crianca' => 'Filho Teste',
            'serie_id' => $serie->id,
        ]);

        $statusLead = TermometroVagasService::obterStatusParaLead($lead);

        $this->assertNotEmpty($statusLead);
        $this->assertTrue($statusLead['tem_escassez']);
        $this->assertSame(TermometroVagasService::STATUS_CRITICO, $statusLead['nivel_mais_critico']);
        $this->assertSame('danger', $statusLead['badge_cor']);
        $this->assertCount(1, $statusLead['series']);
        $this->assertSame($serie->id, $statusLead['series'][0]['serie_id']);
        $this->assertSame(1, $statusLead['series'][0]['vagas_restantes']);
    }

    public function test_gerar_prompt_escassez_injeta_informacoes_urgencia(): void
    {
        $serie = $this->criarSerie('2º Ano');
        $turma = Turma::factory()->create([
            'serie_id' => $serie->id,
            'vagas_maximas' => 10,
        ]);

        // 8 matrículas ocupadas -> 2 vagas restantes
        for ($i = 0; $i < 8; $i++) {
            Matricula::factory()->create([
                'turma_id' => $turma->id,
                'situacao' => 'ativa',
            ]);
        }

        $lead = Interessado::factory()->create();
        InteressadoDependente::create([
            'interessado_id' => $lead->id,
            'nome_crianca' => 'Filho Urgente',
            'serie_id' => $serie->id,
        ]);

        $prompt = TermometroVagasService::gerarPromptEscassez($lead);

        $this->assertNotNull($prompt);
        $this->assertStringContainsString('URGÊNCIA REAL DE VAGAS NA ESCOLA', $prompt);
        $this->assertStringContainsString('2º Ano', $prompt);
        $this->assertStringContainsString('Restam apenas 2 vagas', $prompt);
        $this->assertStringContainsString('Atenção: As turmas pretendidas estão com alta procura', $prompt);
    }

    public function test_turma_sem_vagas_maximas_definidas_utiliza_padrao_25(): void
    {
        $serie = $this->criarSerie('Infantil 4');
        Turma::factory()->create([
            'serie_id' => $serie->id,
            'vagas_maximas' => null, // Sem valor definido
        ]);

        $dados = TermometroVagasService::calcularVagasPorSerie($serie->id);

        $this->assertSame(TermometroVagasService::VAGAS_PADRAO_TURMA, $dados['capacidade_total']);
        $this->assertSame(TermometroVagasService::VAGAS_PADRAO_TURMA, $dados['vagas_restantes']);
        $this->assertEquals(0.0, $dados['taxa_ocupacao']);
        $this->assertSame(TermometroVagasService::STATUS_DISPONIVEL, $dados['nivel_escassez']);
    }

    private function periodo(string $nome, int $inicioEmDias, int $fimEmDias): PeriodoLetivo
    {
        return PeriodoLetivo::factory()->create([
            'nome' => $nome,
            'data_inicio' => today()->addDays($inicioEmDias),
            'data_fim' => today()->addDays($fimEmDias),
        ]);
    }

    private function matricular(Turma $turma, int $quantidade): void
    {
        for ($i = 0; $i < $quantidade; $i++) {
            Matricula::factory()->create(['turma_id' => $turma->id, 'situacao' => 'ativa', 'data_desativacao' => null]);
        }
    }

    private function leadComDependenteNa(Serie $serie): Interessado
    {
        $lead = Interessado::factory()->create();
        InteressadoDependente::create(['interessado_id' => $lead->id, 'nome_crianca' => 'Filho', 'serie_id' => $serie->id]);

        return $lead;
    }

    public function test_so_conta_as_turmas_do_proximo_periodo_quando_ele_ja_tem_turma_aberta(): void
    {
        $serie = $this->criarSerie('5º Ano');
        $atual = $this->periodo('Em curso', -200, 60);
        $proximo = $this->periodo('Próximo', 90, 450);

        // Turma do período em curso, lotada, e turma do próximo período: as duas "Ativa" (nenhuma é "Planejada").
        $cheia = Turma::factory()->create(['serie_id' => $serie->id, 'periodo_letivo_id' => $atual->id, 'vagas_maximas' => 10]);
        $this->matricular($cheia, 10);
        $nova = Turma::factory()->create(['serie_id' => $serie->id, 'periodo_letivo_id' => $proximo->id, 'vagas_maximas' => 20]);
        $this->matricular($nova, 2);

        $dados = TermometroVagasService::calcularVagasPorSerie($serie->id);

        $this->assertSame(20, $dados['capacidade_total']);
        $this->assertSame(18, $dados['vagas_restantes']);
        $this->assertSame($proximo->id, $dados['periodo_letivo_id']);
        $this->assertSame('Próximo', $dados['periodo_letivo_nome']);
        $this->assertSame(TermometroVagasService::STATUS_DISPONIVEL, $dados['nivel_escassez']);
    }

    public function test_sem_periodo_futuro_usa_o_periodo_em_curso(): void
    {
        $serie = $this->criarSerie('6º Ano');
        $encerrado = $this->periodo('Encerrado', -400, -30);
        $emCurso = $this->periodo('Em curso', -100, 200);

        $velha = Turma::factory()->create(['serie_id' => $serie->id, 'periodo_letivo_id' => $encerrado->id, 'vagas_maximas' => 30]);
        $this->matricular($velha, 1);
        $atual = Turma::factory()->create(['serie_id' => $serie->id, 'periodo_letivo_id' => $emCurso->id, 'vagas_maximas' => 10]);
        $this->matricular($atual, 8);

        $dados = TermometroVagasService::calcularVagasPorSerie($serie->id);

        $this->assertSame($emCurso->id, $dados['periodo_letivo_id']);
        $this->assertSame(10, $dados['capacidade_total']);
        $this->assertSame(2, $dados['vagas_restantes']);
    }

    public function test_capacidade_e_marcada_como_estimada_sem_turma_ou_sem_vagas_maximas(): void
    {
        $semTurma = $this->criarSerie('Sem Turma');
        $this->assertTrue(TermometroVagasService::calcularVagasPorSerie($semTurma->id)['capacidade_estimada']);

        $semVagas = $this->criarSerie('Sem Vagas');
        Turma::factory()->create(['serie_id' => $semVagas->id, 'vagas_maximas' => null]);
        $this->assertTrue(TermometroVagasService::calcularVagasPorSerie($semVagas->id)['capacidade_estimada']);

        $definida = $this->criarSerie('Definida');
        Turma::factory()->create(['serie_id' => $definida->id, 'vagas_maximas' => 12]);
        $this->assertFalse(TermometroVagasService::calcularVagasPorSerie($definida->id)['capacidade_estimada']);
    }

    public function test_capacidade_estimada_nao_gera_escassez_nem_prompt_de_urgencia(): void
    {
        $serie = $this->criarSerie('Infantil 3');
        $turma = Turma::factory()->create(['serie_id' => $serie->id, 'vagas_maximas' => null]);
        // 24 matrículas sobre o padrão de 25 pareceria "última vaga" sem que a escola tenha definido a capacidade.
        $this->matricular($turma, 24);
        $lead = $this->leadComDependenteNa($serie);

        $status = TermometroVagasService::obterStatusParaLead($lead);

        $this->assertFalse($status['tem_escassez']);
        $this->assertSame('gray', $status['badge_cor']);
        $this->assertSame('Capacidade não definida', $status['texto_destaque']);
        $this->assertNull(TermometroVagasService::gerarPromptEscassez($lead), 'Sem capacidade definida não há número confiável para o prompt.');
    }

    public function test_prompt_traz_periodo_e_data_e_orienta_a_nao_inventar_numeros(): void
    {
        $serie = $this->criarSerie('4º Ano');
        $proximo = $this->periodo('Ano Letivo 2027', 60, 420);
        $turma = Turma::factory()->create(['serie_id' => $serie->id, 'periodo_letivo_id' => $proximo->id, 'vagas_maximas' => 10]);
        $this->matricular($turma, 9);

        $prompt = TermometroVagasService::gerarPromptEscassez($this->leadComDependenteNa($serie));

        $this->assertStringContainsString('turmas de Ano Letivo 2027', $prompt);
        $this->assertStringContainsString('Posição em '.now()->format('d/m/Y'), $prompt);
        $this->assertStringContainsString('Restam apenas 1 vagas', $prompt);
        $this->assertStringContainsString('não invente prazo, desconto nem outro número de vagas', $prompt);
        $this->assertStringNotContainsString('USE ISSO NA ABORDAGEM', $prompt);
    }

    public function test_prompt_sem_escassez_nao_diz_restam_apenas_nem_estimula_urgencia(): void
    {
        $serie = $this->criarSerie('7º Ano');
        $turma = Turma::factory()->create(['serie_id' => $serie->id, 'vagas_maximas' => 30]);
        $this->matricular($turma, 3);

        $prompt = TermometroVagasService::gerarPromptEscassez($this->leadComDependenteNa($serie));

        $this->assertStringContainsString('27 vagas disponíveis.', $prompt);
        $this->assertStringNotContainsString('Restam apenas', $prompt);
        $this->assertStringNotContainsString('URGÊNCIA', $prompt);
        $this->assertStringContainsString('não use argumento de urgência', $prompt);
    }

    public function test_modal_do_termometro_mostra_periodo_e_marca_capacidade_estimada(): void
    {
        $comPeriodo = $this->criarSerie('8º Ano');
        $periodo = $this->periodo('Ano Letivo 2027', 60, 420);
        Turma::factory()->create(['serie_id' => $comPeriodo->id, 'periodo_letivo_id' => $periodo->id, 'vagas_maximas' => 20]);
        $estimada = $this->criarSerie('9º Ano');

        $html = view('filament.crm.modal-termometro-vagas')->render();

        $this->assertStringContainsString('turmas de Ano Letivo 2027', $html);
        $this->assertSame(1, preg_match_all('#>\s*capacidade estimada\s*</span>#', $html), 'Só a série sem turma deve levar o selo de capacidade estimada.');
        $this->assertStringContainsString($estimada->nome, $html);
    }

    public function test_kanban_nao_mostra_alerta_de_escassez_para_capacidade_estimada(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['activated_at' => now()]);
        $admin->assignRole('super_admin');
        $status = StatusInteressado::create(['nome' => 'Novo', 'ordem' => 1]);
        $origem = OrigemInteressado::create(['nome' => 'Instagram']);

        $serie = $this->criarSerie('Série Estimada');
        $turma = Turma::factory()->create(['serie_id' => $serie->id, 'vagas_maximas' => null]);
        $this->matricular($turma, 24);

        $lead = $this->leadComDependenteNa($serie);
        $lead->update(['status_interessado_id' => $status->id, 'origem_interessado_id' => $origem->id, 'usuario_id' => $admin->id]);

        $this->actingAs($admin)->get('/admin/interessados/kanban')
            ->assertOk()
            ->assertSee('Série Estimada')
            // O selo do card é "(🔥 N vagas)" / "(⛔ 0 vagas)" / "(🟡 N restam)"; a ajuda da página cita 🔥 em outro contexto.
            ->assertDontSee('(🔥')
            ->assertDontSee('(⛔')
            ->assertDontSee('(🟡');
    }

    public function test_kanban_renderiza_com_sucesso_com_alertas_de_vagas(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['activated_at' => now()]);
        $admin->assignRole('super_admin');

        $status = StatusInteressado::create(['nome' => 'Novo', 'ordem' => 1]);
        $origem = OrigemInteressado::create(['nome' => 'Instagram']);

        $serie = $this->criarSerie('1º Ano');
        $turma = Turma::factory()->create([
            'serie_id' => $serie->id,
            'vagas_maximas' => 10,
        ]);
        for ($i = 0; $i < 9; $i++) {
            Matricula::factory()->create([
                'turma_id' => $turma->id,
                'situacao' => 'ativa',
            ]);
        }

        // Lead 1: com dependente em série crítica
        $lead1 = Interessado::factory()->create([
            'status_interessado_id' => $status->id,
            'origem_interessado_id' => $origem->id,
            'usuario_id' => $admin->id,
        ]);
        InteressadoDependente::create([
            'interessado_id' => $lead1->id,
            'nome_crianca' => 'Criança 1',
            'serie_id' => $serie->id,
        ]);

        // Lead 2: dependente sem série definida
        $lead2 = Interessado::factory()->create([
            'status_interessado_id' => $status->id,
            'origem_interessado_id' => $origem->id,
            'usuario_id' => $admin->id,
        ]);
        InteressadoDependente::create([
            'interessado_id' => $lead2->id,
            'nome_crianca' => 'Criança Sem Série',
            'serie_id' => null,
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin/interessados/kanban');

        $response->assertOk();
        $response->assertSee('Funil de Vendas (CRM)');
        $response->assertSee('1º Ano');
        $response->assertSee('🔥 1 vagas');
    }
}
