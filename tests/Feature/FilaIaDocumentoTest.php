<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SituacaoDocumento;
use App\Filament\Widgets\QueueSupervisorWidget;
use App\Jobs\ValidarDocumentoComIaJob;
use App\Models\DocumentoInserido;
use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\TipoDocumento;
use App\Models\User;
use App\Services\DocumentoIaService;
use App\Services\GeminiAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Mockery;
use Tests\TestCase;

/**
 * A análise de documentos por IA (Gemini) leva de segundos a minutos e disputava a fila padrão com e-mails e
 * notificações. Roda numa fila própria, com limite de tempo por tentativa, e avisa o consultor se esgotar as tentativas.
 */
class FilaIaDocumentoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Sleep::fake();
        config(['services.gemini.key' => 'chave-de-teste']);
    }

    private function documento(?User $consultor = null): DocumentoInserido
    {
        $lead = Interessado::create([
            'pessoa_id' => Pessoa::factory()->create(['nome' => 'Família Teste'])->id,
            'usuario_id' => $consultor?->id,
            'status_interessado_id' => StatusInteressado::create(['nome' => 'Novo', 'cor' => 'info', 'ordem' => 1, 'is_final' => false, 'is_ganho' => false])->id,
            'origem_interessado_id' => OrigemInteressado::create(['nome' => 'Site'])->id,
        ]);

        $caminho = 'documentos_candidatos/'.$lead->id.'/certidao.pdf';
        Storage::disk('local')->put($caminho, 'conteudo-falso');

        return DocumentoInserido::create([
            'interessado_id' => $lead->id,
            'tipo_documento_id' => TipoDocumento::create(['nome' => 'Certidão de Nascimento', 'flag_obrigatorio' => true, 'status' => 'ativo'])->id,
            'arquivo_path' => $caminho,
            'nome_arquivo_original' => 'certidao.pdf',
            'status' => SituacaoDocumento::EM_ANALISE,
        ]);
    }

    // ─── Fila dedicada ──────────────────────────────────────────

    public function test_job_vai_para_a_fila_de_ia_configurada(): void
    {
        $this->assertSame('ia', (new ValidarDocumentoComIaJob(1))->queue);

        config(['crm.fila_ia' => 'documentos']);
        $this->assertSame('documentos', (new ValidarDocumentoComIaJob(1))->queue);
    }

    public function test_despacho_publica_o_job_na_fila_de_ia(): void
    {
        Queue::fake();

        ValidarDocumentoComIaJob::dispatch(10);

        Queue::assertPushedOn('ia', ValidarDocumentoComIaJob::class);
    }

    public function test_tempo_limite_do_job_fica_abaixo_do_retry_after_da_fila(): void
    {
        $job = new ValidarDocumentoComIaJob(1);
        $retryAfter = (int) config('queue.connections.database.retry_after');

        // Se o job pudesse rodar além do retry_after, a fila o entregaria a outro worker com o primeiro ainda ativo.
        $this->assertLessThan($retryAfter, $job->timeout);
        // E precisa caber o orçamento de tempo que o Gemini pode usar na análise do documento.
        $this->assertGreaterThan((int) config('services.gemini.orcamento_documento_segundos'), $job->timeout);
    }

    public function test_agendador_inclui_um_worker_dedicado_a_fila_de_ia(): void
    {
        Artisan::call('schedule:list');
        $saida = Artisan::output();

        $this->assertStringContainsString('queue:work --queue=ia', $saida);
        $this->assertStringContainsString('--timeout=85', $saida);
        // O worker da fila padrão continua existindo, sem competir com o da IA.
        $this->assertMatchesRegularExpression('/queue:work --stop-when-empty/', $saida);
    }

    public function test_botao_processar_fila_agora_inclui_a_fila_de_ia(): void
    {
        $this->assertSame('default,ia', QueueSupervisorWidget::filasParaProcessar());

        // Configurada com o mesmo nome da padrão, não duplica; vazia, é ignorada.
        config(['crm.fila_ia' => 'default']);
        $this->assertSame('default', QueueSupervisorWidget::filasParaProcessar());

        config(['crm.fila_ia' => '']);
        $this->assertSame('default', QueueSupervisorWidget::filasParaProcessar());
    }

    // ─── Falha após as tentativas ───────────────────────────────

    public function test_esgotadas_as_tentativas_o_consultor_e_avisado(): void
    {
        $consultor = User::factory()->create();
        $documento = $this->documento($consultor);

        Log::spy();

        (new ValidarDocumentoComIaJob($documento->id))->failed(new \RuntimeException('Gemini fora do ar'));

        $notificacao = $consultor->notifications()->firstOrFail();
        $this->assertSame('Análise por IA indisponível', $notificacao->data['title']);
        $this->assertStringContainsString('Certidão de Nascimento', $notificacao->data['body']);
        $this->assertStringContainsString('Família Teste', $notificacao->data['body']);

        Log::shouldHaveReceived('error')->once();
    }

    public function test_falha_sem_consultor_so_registra_no_log_e_nao_quebra(): void
    {
        $documento = $this->documento(null);

        (new ValidarDocumentoComIaJob($documento->id))->failed(new \RuntimeException('Gemini fora do ar'));

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_falha_de_documento_excluido_nao_quebra(): void
    {
        (new ValidarDocumentoComIaJob(999999))->failed(null);

        $this->assertDatabaseCount('notifications', 0);
    }

    // ─── Orçamento de tempo do Gemini ───────────────────────────

    public function test_sem_orcamento_so_tenta_uma_vez_e_desiste(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'overloaded']], 503)]);

        try {
            app(GeminiAgentService::class)->callGeminiApi(['contents' => []], orcamentoSegundos: 0);
            $this->fail('Era esperada uma exceção.');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('alta demanda', $e->getMessage());
        }

        Http::assertSentCount(1);
    }

    public function test_com_orcamento_folgado_percorre_todos_os_modelos_em_duas_rodadas(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'overloaded']], 503)]);

        try {
            app(GeminiAgentService::class)->callGeminiApi(['contents' => []], orcamentoSegundos: 600);
            $this->fail('Era esperada uma exceção.');
        } catch (\Throwable) {
            // esperado: todos os modelos indisponíveis
        }

        // 5 modelos de contingência x 2 rodadas.
        Http::assertSentCount(10);
    }

    public function test_orcamento_padrao_vem_da_configuracao(): void
    {
        config(['services.gemini.orcamento_segundos' => 1]);
        // A primeira tentativa já consome mais que o orçamento de 1 s, então não há segunda.
        Http::fake(function () {
            usleep(1_100_000);

            return Http::response(['error' => ['message' => 'overloaded']], 503);
        });

        try {
            app(GeminiAgentService::class)->callGeminiApi(['contents' => []], timeout: 1);
        } catch (\Throwable) {
            // esperado
        }

        Http::assertSentCount(1);
    }

    public function test_cada_tentativa_respeita_o_timeout_pedido_e_o_orcamento_padrao_nao_o_corta(): void
    {
        config(['services.gemini.orcamento_segundos' => 60]);
        $timeouts = [];
        Http::fake(function ($request, array $options) use (&$timeouts) {
            $timeouts[] = $options['timeout'];

            return Http::response(['error' => ['message' => 'overloaded']], 503);
        });

        // Resumo de conversa com áudio pede 120 s por tentativa: o orçamento padrão (60 s) não pode reduzi-lo.
        try {
            app(GeminiAgentService::class)->callGeminiApi(['contents' => []], 120);
        } catch (\Throwable) {
            // esperado
        }
        $this->assertSame(120, $timeouts[0]);

        // Chamadas comuns: 45 s por tentativa.
        $timeouts = [];
        try {
            app(GeminiAgentService::class)->callGeminiApi(['contents' => []]);
        } catch (\Throwable) {
            // esperado
        }
        $this->assertSame(45, $timeouts[0]);
        $this->assertLessThanOrEqual(45, max($timeouts));

        // Com orçamento explícito menor que o timeout, a tentativa nunca passa do orçamento restante.
        $timeouts = [];
        try {
            app(GeminiAgentService::class)->callGeminiApi(['contents' => []], orcamentoSegundos: 20);
        } catch (\Throwable) {
            // esperado
        }
        $this->assertSame(20, $timeouts[0]);
    }

    public function test_resposta_valida_na_primeira_tentativa_nao_consome_mais_nada(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'ok']]]]]], 200)]);

        $resposta = app(GeminiAgentService::class)->callGeminiApi(['contents' => []], orcamentoSegundos: 0);

        $this->assertSame('ok', $resposta['candidates'][0]['content']['parts'][0]['text']);
        Http::assertSentCount(1);
    }

    public function test_analise_de_documento_usa_o_orcamento_proprio_de_documentos(): void
    {
        config(['services.gemini.orcamento_documento_segundos' => 70]);
        $documento = $this->documento(User::factory()->create());

        $gemini = Mockery::mock(GeminiAgentService::class);
        $gemini->shouldReceive('callGeminiApi')
            ->once()
            ->with(Mockery::type('array'), 45, 70)
            ->andReturn(['candidates' => [['content' => ['parts' => [['text' => json_encode([
                'documento_identificado' => 'certidao_nascimento',
                'confere_com_solicitado' => true,
                'qualidade' => ['legivel' => true],
            ])]]]]]]);

        (new DocumentoIaService($gemini))->analisarDocumento($documento);

        $this->assertNotNull($documento->fresh()->analisado_ia_em);
    }
}
