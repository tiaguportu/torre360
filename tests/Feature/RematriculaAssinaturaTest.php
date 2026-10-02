<?php

namespace Tests\Feature;

use App\Enums\StatusRematricula;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\PeriodoRematricula;
use App\Models\Pessoa;
use App\Models\Rematricula;
use App\Models\TemplateContrato;
use App\Models\Turma;
use App\Models\User;
use App\Services\AssinafyService;
use App\Services\RematriculaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RematriculaAssinaturaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // O .env local pode ter credenciais reais do Assinafy: o webhook não pode consultar a API de verdade.
        Http::preventStrayRequests();
        config(['services.assinafy.key' => '']);
    }

    /**
     * @return array{rematricula: Rematricula}
     */
    private function prepararRematriculaComTemplate(): array
    {
        $origem = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);
        $destino = PeriodoLetivo::create(['nome' => '2027', 'data_inicio' => '2027-02-01', 'data_fim' => '2027-12-15']);

        $templateContrato = TemplateContrato::create([
            'nome' => 'Contrato Padrão 2027',
            'conteudo' => '<p>Termos.</p>',
            'is_padrao' => true,
        ]);

        $periodo = PeriodoRematricula::create([
            'nome' => 'Rematrícula 2027',
            'periodo_letivo_origem_id' => $origem->id,
            'periodo_letivo_destino_id' => $destino->id,
            'template_contrato_id' => $templateContrato->id,
            'valor_taxa' => 1200.00,
            'quantidade_parcelas_padrao' => 12,
            'valor_entrada_padrao' => 0,
            'data_inicio' => now()->subDays(2)->toDateString(),
            'data_fim' => now()->addDays(15)->toDateString(),
            'is_ativo' => true,
        ]);

        $aluno = Pessoa::create(['nome' => 'Aluno Assinatura Teste', 'cpf' => '11122233344']);
        $turmaOrigem = Turma::create(['nome' => '4º Ano', 'periodo_letivo_id' => $origem->id]);
        $matriculaOrigem = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turmaOrigem->id,
            'periodo_letivo_id' => $origem->id,
            'situacao' => 'ativa',
        ]);
        $turmaDestino = Turma::create(['nome' => '5º Ano', 'periodo_letivo_id' => $destino->id]);

        $user = User::factory()->create();

        $service = app(RematriculaService::class);
        $rematricula = $service->iniciarOuObter($matriculaOrigem, $periodo, $user);
        $rematricula->update(['turma_destino_id' => $turmaDestino->id, 'status' => StatusRematricula::DadosConfirmados]);

        return compact('rematricula');
    }

    public function test_efetivar_com_assinafy_configurado_fica_aguardando_assinatura(): void
    {
        $this->mock(AssinafyService::class, function ($mock) {
            $mock->shouldReceive('enviarContrato')->once()->andReturn(['success' => true, 'message' => 'Enviado']);
        });

        ['rematricula' => $rematricula] = $this->prepararRematriculaComTemplate();

        app(RematriculaService::class)->efetivar($rematricula);

        $rematricula->refresh();
        $this->assertEquals(StatusRematricula::AguardandoAssinatura, $rematricula->status);
        $this->assertNull($rematricula->data_confirmacao);
    }

    public function test_webhook_assinafy_confirma_rematricula_quando_contrato_e_assinado(): void
    {
        ['rematricula' => $rematricula] = $this->prepararRematriculaComTemplate();

        app(RematriculaService::class)->efetivar($rematricula);
        $rematricula->refresh();
        $contrato = $rematricula->contrato;

        // Simula o envio ter sido feito (campo normalmente preenchido por enviarContrato())
        $contrato->update(['assinafy_id' => 'DOC-TESTE-123']);

        $processado = app(AssinafyService::class)->handleWebhook([
            'event' => 'document_ready',
            'object' => ['id' => 'DOC-TESTE-123'],
        ]);

        $this->assertTrue($processado);

        $rematricula->refresh();
        $this->assertEquals(StatusRematricula::Confirmada, $rematricula->status);
        $this->assertNotNull($rematricula->data_confirmacao);
    }

    public function test_webhook_nao_confirma_rematricula_ja_confirmada_de_novo(): void
    {
        ['rematricula' => $rematricula] = $this->prepararRematriculaComTemplate();
        app(RematriculaService::class)->efetivar($rematricula);
        $rematricula->refresh();
        $contrato = $rematricula->contrato;
        $contrato->update(['assinafy_id' => 'DOC-TESTE-456']);

        app(AssinafyService::class)->handleWebhook(['event' => 'document_ready', 'object' => ['id' => 'DOC-TESTE-456']]);
        $primeiraConfirmacao = $rematricula->fresh()->data_confirmacao;

        app(AssinafyService::class)->handleWebhook(['event' => 'document_ready', 'object' => ['id' => 'DOC-TESTE-456']]);
        $segundaConfirmacao = $rematricula->fresh()->data_confirmacao;

        $this->assertTrue($primeiraConfirmacao->equalTo($segundaConfirmacao));
    }
}
