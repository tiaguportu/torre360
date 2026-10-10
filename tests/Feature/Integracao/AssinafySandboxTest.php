<?php

declare(strict_types=1);

namespace Tests\Feature\Integracao;

use App\Models\Contrato;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Services\AssinafyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Integração REAL com o SANDBOX do Assinafy. Fica desligada: só roda quando pedida na linha de comando, porque
 * cada execução fala com a API de verdade.
 *
 *   RODAR_SANDBOX_ASSINAFY=1 php artisan test --filter=AssinafySandboxTest
 *
 * Credenciais: ASSINAFY_SANDBOX_API_URL, ASSINAFY_SANDBOX_API_KEY e ASSINAFY_SANDBOX_ACCOUNT_ID no `.env` (nomes
 * próprios; as ASSINAFY_API_KEY/ACCOUNT_ID de produção são zeradas pelo phpunit.xml e nunca entram aqui).
 *
 * Salvaguardas: a URL precisa ser do host do sandbox (senão o teste falha antes de qualquer requisição) e a rede
 * fica bloqueada para qualquer outro host, inclusive o fallback de produção que o AssinafyService tenta nas leituras.
 */
class AssinafySandboxTest extends TestCase
{
    use RefreshDatabase;

    private const HOST_SANDBOX = 'sandbox.assinafy.com.br';

    private string $url = '';

    private string $chave = '';

    private string $conta = '';

    protected function setUp(): void
    {
        // O flag vem do ambiente do processo (não do .env, para não ficar ligado sem querer) e é lido antes de
        // subir a aplicação: sem ele o teste é pulado sem pagar o custo do banco em memória.
        if (! filter_var(getenv('RODAR_SANDBOX_ASSINAFY'), FILTER_VALIDATE_BOOLEAN)) {
            $this->markTestSkipped('Integração real com o sandbox do Assinafy: rode com RODAR_SANDBOX_ASSINAFY=1.');
        }

        parent::setUp();

        $this->url = rtrim((string) env('ASSINAFY_SANDBOX_API_URL', ''), '/');
        $this->chave = (string) env('ASSINAFY_SANDBOX_API_KEY', '');
        $this->conta = (string) env('ASSINAFY_SANDBOX_ACCOUNT_ID', '');

        if ($this->url === '' || $this->chave === '' || $this->conta === '') {
            $this->fail('Defina ASSINAFY_SANDBOX_API_URL, ASSINAFY_SANDBOX_API_KEY e ASSINAFY_SANDBOX_ACCOUNT_ID no .env.');
        }

        if (parse_url($this->url, PHP_URL_HOST) !== self::HOST_SANDBOX) {
            $this->fail('ASSINAFY_SANDBOX_API_URL deve apontar para '.self::HOST_SANDBOX.'. Recusando para não tocar em outro ambiente.');
        }

        config([
            'services.assinafy.key' => $this->chave,
            'services.assinafy.account_id' => $this->conta,
            'services.assinafy.url' => $this->url,
        ]);

        Http::preventStrayRequests();
        Http::allowStrayRequests(['https://'.self::HOST_SANDBOX.'/*']);
    }

    public function test_o_sandbox_aceita_a_chave_e_a_conta_configuradas(): void
    {
        $resposta = Http::withHeaders(['X-Api-Key' => $this->chave, 'Accept' => 'application/json'])
            ->get("{$this->url}/accounts/{$this->conta}/documents", ['search' => 'Contrato - Escola Torre de Marfim - Aluno Sandbox']);

        // Só o status na mensagem: o corpo da resposta não é impresso.
        $this->assertTrue($resposta->successful(), 'O sandbox recusou a chave ou a conta (HTTP '.$resposta->status().').');
    }

    public function test_enviar_contrato_cria_o_documento_no_sandbox_e_a_consulta_acompanha_o_status(): void
    {
        // Nome do aluno e id do contrato fixos (banco em memória: o contrato é sempre o nº 1), então o nome do PDF é
        // sempre o mesmo e as execuções seguintes reaproveitam o documento em vez de acumular lixo no sandbox.
        // E-mail em example.com (domínio reservado): nunca chega a uma pessoa real.
        $aluno = Pessoa::factory()->create(['nome' => 'Aluno Sandbox Torre360']);
        $responsavel = Pessoa::factory()->create(['nome' => 'Responsavel Sandbox Torre360', 'email' => 'sandbox-torre360@example.com']);
        $matricula = Matricula::factory()->create(['pessoa_id' => $aluno->id]);

        $contrato = Contrato::create(['valor_total' => 1000.00, 'matricula_id' => $matricula->id]);
        $contrato->responsaveisFinanceiros()->create(['pessoa_id' => $responsavel->id, 'percentual' => 100]);

        $envio = (new AssinafyService)->enviarContrato($contrato);

        $this->assertTrue($envio['success'], 'Falha no envio ao sandbox: '.($envio['message'] ?? 'sem mensagem'));
        $this->assertNotEmpty($envio['redirect_url'], 'O sandbox não devolveu o link de assinatura.');

        $contrato->refresh();
        $this->assertNotEmpty($contrato->assinafy_id);
        $this->assertSame('enviado', $contrato->assinafy_status);

        // A leitura do documento recém-criado: valida a API de consulta e o mapeamento de status do serviço.
        $consulta = (new AssinafyService)->consultarEAtualizarStatusSignatarios($contrato);

        $this->assertTrue($consulta['success'], 'Falha ao consultar o documento no sandbox: '.($consulta['message'] ?? 'sem mensagem'));
        $this->assertNotEmpty($consulta['status'], 'O sandbox não informou o status do documento.');
    }
}
