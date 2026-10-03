<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\Matricula;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\Unidade;
use App\Services\TermometroVagasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
