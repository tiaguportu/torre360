<?php

namespace Tests\Feature;

use App\Enums\StatusRematricula;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\PeriodoRematricula;
use App\Models\Pessoa;
use App\Models\TemplateContrato;
use App\Models\Turma;
use App\Models\User;
use App\Services\RematriculaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RematriculaTest extends TestCase
{
    use RefreshDatabase;

    public function test_periodo_rematricula_detecta_vigencia_corretamente(): void
    {
        $origem = PeriodoLetivo::create([
            'nome' => '2026',
            'data_inicio' => '2026-02-01',
            'data_fim' => '2026-12-15',
        ]);

        $destino = PeriodoLetivo::create([
            'nome' => '2027',
            'data_inicio' => '2027-02-01',
            'data_fim' => '2027-12-15',
        ]);

        $periodoAtivo = PeriodoRematricula::create([
            'nome' => 'Rematrícula 2027',
            'periodo_letivo_origem_id' => $origem->id,
            'periodo_letivo_destino_id' => $destino->id,
            'data_inicio' => now()->subDays(5)->toDateString(),
            'data_fim' => now()->addDays(20)->toDateString(),
            'is_ativo' => true,
        ]);

        $this->assertTrue($periodoAtivo->isAberto());

        $periodoInativo = PeriodoRematricula::create([
            'nome' => 'Campanha Encerrada',
            'periodo_letivo_origem_id' => $origem->id,
            'periodo_letivo_destino_id' => $destino->id,
            'data_inicio' => now()->subDays(30)->toDateString(),
            'data_fim' => now()->subDays(5)->toDateString(),
            'is_ativo' => true,
        ]);

        $this->assertFalse($periodoInativo->isAberto());
    }

    public function test_service_inicia_e_efetiva_rematricula_com_sucesso(): void
    {
        $origem = PeriodoLetivo::create([
            'nome' => '2026',
            'data_inicio' => '2026-02-01',
            'data_fim' => '2026-12-15',
        ]);

        $destino = PeriodoLetivo::create([
            'nome' => '2027',
            'data_inicio' => '2027-02-01',
            'data_fim' => '2027-12-15',
        ]);

        $templateContrato = TemplateContrato::create([
            'nome' => 'Contrato Padrão 2027',
            'conteudo' => '<p>Termos do contrato escolar.</p>',
            'is_padrao' => true,
        ]);

        $periodo = PeriodoRematricula::create([
            'nome' => 'Rematrícula 2027 Oficial',
            'periodo_letivo_origem_id' => $origem->id,
            'periodo_letivo_destino_id' => $destino->id,
            'template_contrato_id' => $templateContrato->id,
            'valor_taxa' => 450.00,
            'data_inicio' => now()->subDays(2)->toDateString(),
            'data_fim' => now()->addDays(15)->toDateString(),
            'is_ativo' => true,
        ]);

        $aluno = Pessoa::create([
            'nome' => 'Gabriel Ferreira',
            'cpf' => '55566677788',
        ]);

        $turmaOrigem = Turma::create([
            'nome' => '4º Ano B',
            'periodo_letivo_id' => $origem->id,
        ]);

        $matriculaOrigem = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turmaOrigem->id,
            'periodo_letivo_id' => $origem->id,
            'situacao' => 'ativa',
        ]);

        $turmaDestino = Turma::create([
            'nome' => '5º Ano B',
            'periodo_letivo_id' => $destino->id,
        ]);

        $user = User::factory()->create();

        $service = app(RematriculaService::class);

        // 1. Inicia processo
        $rematricula = $service->iniciarOuObter($matriculaOrigem, $periodo, $user);
        $this->assertEquals(StatusRematricula::Iniciada, $rematricula->status);

        // 2. Define destino e dados
        $rematricula->update([
            'turma_destino_id' => $turmaDestino->id,
            'status' => StatusRematricula::DadosConfirmados,
        ]);

        // 3. Efetiva rematrícula
        $novaMatricula = $service->efetivar($rematricula);

        $this->assertNotNull($novaMatricula);
        $this->assertEquals($destino->id, $novaMatricula->periodo_letivo_id);
        $this->assertEquals($turmaDestino->id, $novaMatricula->turma_id);
        $this->assertEquals($aluno->id, $novaMatricula->pessoa_id);

        $rematricula->refresh();
        // Sem Assinafy configurado (padrão em teste), o envio para assinatura falha
        // graciosamente e a rematrícula fica em DadosConfirmados para a secretaria
        // resolver manualmente — só vira Confirmada quando o contrato é assinado
        // (ver AssinafyService::handleWebhook() e RematriculaAssinaturaTest).
        $this->assertEquals(StatusRematricula::DadosConfirmados, $rematricula->status);
        $this->assertNotNull($rematricula->nova_matricula_id);
        $this->assertNotNull($rematricula->contrato_id);

        // A cobrança (faturas) já deve ter sido gerada automaticamente
        $this->assertSame(12, $rematricula->contrato->faturas()->count());
    }
}
