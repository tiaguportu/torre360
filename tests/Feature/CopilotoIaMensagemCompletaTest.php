<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\User;
use App\Services\CrmIaVendasService;
use App\Services\GeminiAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Mockery;
use Tests\TestCase;

/**
 * O Copiloto IA nunca pode entregar uma mensagem cortada: o `gemini-2.5-flash` gasta tokens de raciocínio do
 * mesmo orçamento de `maxOutputTokens`, e a geração terminava em MAX_TOKENS com o texto pela metade.
 */
class CopilotoIaMensagemCompletaTest extends TestCase
{
    use RefreshDatabase;

    private const TRECHO_CORTADO = 'Olá, Mariana! Que alegria saber do interesse na Escola Torre de Marfim para o Lucas. Nosso Tour Pedagógico é uma ótima forma de';

    private const MENSAGEM_COMPLETA = 'Olá, Mariana! Que alegria saber do interesse na Escola Torre de Marfim para o Lucas. Nosso Tour Pedagógico é uma ótima forma de conhecer a escola. Que dia seria melhor para vocês?';

    private function interessado(): Interessado
    {
        $pessoa = Pessoa::factory()->create(['nome' => 'Mariana Oliveira', 'telefone' => '11988887777']);

        $interessado = Interessado::create([
            'pessoa_id' => $pessoa->id,
            'usuario_id' => User::factory()->create()->id,
            'status_interessado_id' => StatusInteressado::factory()->create(['nome' => 'Novo Contato'])->id,
            'origem_interessado_id' => OrigemInteressado::firstOrCreate(['nome' => 'Site'])->id,
            'temperatura' => 'morno',
            'lead_score' => 60,
        ]);

        InteressadoDependente::create([
            'interessado_id' => $interessado->id,
            'nome_crianca' => 'Lucas Oliveira',
            'data_nascimento' => now()->subYears(7)->toDateString(),
            'serie_pretendida' => '2º Ano Fundamental',
        ]);

        return $interessado;
    }

    /** @return array<string, mixed> */
    private function resposta(string $texto, string $finishReason = 'STOP'): array
    {
        return ['candidates' => [[
            'content' => ['parts' => [['text' => $texto]]],
            'finishReason' => $finishReason,
        ]]];
    }

    /**
     * Mocka o Gemini devolvendo as respostas em sequência e guarda cada payload recebido em $payloads.
     *
     * @param  array<int, array<string, mixed>>  $respostas
     * @param  array<int, array<string, mixed>>  $payloads
     */
    private function mockGemini(array $respostas, array &$payloads): void
    {
        $mock = Mockery::mock(GeminiAgentService::class);
        $mock->shouldReceive('callGeminiApi')
            ->times(count($respostas))
            ->withArgs(function (array $payload) use (&$payloads): bool {
                $payloads[] = $payload;

                return true;
            })
            ->andReturn(...$respostas);

        $this->app->instance(GeminiAgentService::class, $mock);
    }

    public function test_copiloto_desliga_o_raciocinio_para_nao_gastar_o_limite_de_tokens(): void
    {
        $payloads = [];
        $this->mockGemini([$this->resposta(self::MENSAGEM_COMPLETA)], $payloads);

        $mensagem = app(CrmIaVendasService::class)->gerarMensagemCopiloto($this->interessado(), 'convite_visita');

        $this->assertSame(self::MENSAGEM_COMPLETA, $mensagem);
        $this->assertSame(0, $payloads[0]['generationConfig']['thinkingConfig']['thinkingBudget']);
    }

    public function test_mensagem_cortada_por_limite_de_tokens_e_refeita_com_mais_espaco(): void
    {
        $payloads = [];
        $this->mockGemini([
            $this->resposta(self::TRECHO_CORTADO, 'MAX_TOKENS'),
            $this->resposta(self::MENSAGEM_COMPLETA),
        ], $payloads);

        $mensagem = app(CrmIaVendasService::class)->gerarMensagemCopiloto($this->interessado(), 'convite_visita');

        $this->assertSame(self::MENSAGEM_COMPLETA, $mensagem);
        $this->assertSame(800, $payloads[0]['generationConfig']['maxOutputTokens']);
        $this->assertSame(3200, $payloads[1]['generationConfig']['maxOutputTokens']);
    }

    public function test_mensagem_que_continua_cortada_nao_e_entregue_e_cai_na_contingencia(): void
    {
        $payloads = [];
        $this->mockGemini([
            $this->resposta(self::TRECHO_CORTADO, 'MAX_TOKENS'),
            $this->resposta(self::TRECHO_CORTADO, 'MAX_TOKENS'),
        ], $payloads);

        $mensagem = app(CrmIaVendasService::class)->gerarMensagemCopiloto($this->interessado(), 'convite_visita');

        $this->assertStringNotContainsString(self::TRECHO_CORTADO, $mensagem);
        $this->assertStringContainsString('Mariana', $mensagem);
        $this->assertStringContainsString('Lucas', $mensagem);
    }

    public function test_thinking_config_so_vai_para_os_modelos_flash_2_5(): void
    {
        config([
            'services.gemini.key' => 'chave-de-teste',
            'services.gemini.model' => 'gemini-2.5-flash',
        ]);
        Http::preventStrayRequests();
        Sleep::fake();

        Http::fake([
            '*models/gemini-2.5-flash:generateContent' => Http::response(['error' => ['message' => 'The model is overloaded.']], 503),
            '*models/gemini-flash-latest:generateContent' => Http::response($this->resposta('ok'), 200),
        ]);

        app(GeminiAgentService::class)->callGeminiApi([
            'contents' => [],
            'generationConfig' => ['thinkingConfig' => ['thinkingBudget' => 0]],
        ]);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'gemini-2.5-flash:')
            && ($request->data()['generationConfig']['thinkingConfig']['thinkingBudget'] ?? null) === 0);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'gemini-flash-latest:')
            && ! isset($request->data()['generationConfig']['thinkingConfig']));
    }
}
