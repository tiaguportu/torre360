<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ContentSecurityPolicy;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContentSecurityPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_por_padrao_aplica_a_base_e_apenas_relata_a_estrita(): void
    {
        $resposta = $this->get('/')->assertOk();

        $enforcada = (string) $resposta->headers->get('Content-Security-Policy');
        $relatorio = (string) $resposta->headers->get('Content-Security-Policy-Report-Only');

        $this->assertStringContainsString("object-src 'none'", $enforcada);
        $this->assertStringContainsString("base-uri 'self'", $enforcada);
        $this->assertStringContainsString("frame-ancestors 'self'", $enforcada);
        $this->assertStringNotContainsString('script-src', $enforcada, 'a política estrita ainda não pode bloquear nada');

        $this->assertStringContainsString("default-src 'self'", $relatorio);
        $this->assertStringContainsString('script-src', $relatorio);
        $this->assertStringContainsString('form-action', $relatorio);
        $this->assertStringContainsString('report-uri '.ContentSecurityPolicy::ROTA_RELATORIO, $relatorio);
    }

    public function test_modo_enforce_bloqueia_com_a_politica_estrita(): void
    {
        config(['seguranca.csp.modo' => 'enforce']);

        $resposta = $this->get('/')->assertOk();

        $this->assertStringContainsString("default-src 'self'", (string) $resposta->headers->get('Content-Security-Policy'));
        $this->assertFalse($resposta->headers->has('Content-Security-Policy-Report-Only'));
    }

    public function test_modo_off_desliga_tudo(): void
    {
        config(['seguranca.csp.modo' => 'off']);

        $resposta = $this->get('/')->assertOk();

        $this->assertFalse($resposta->headers->has('Content-Security-Policy'));
        $this->assertFalse($resposta->headers->has('Content-Security-Policy-Report-Only'));
    }

    public function test_origens_extras_da_configuracao_entram_na_politica_estrita(): void
    {
        config(['seguranca.csp.extra.script-src' => 'https://exemplo.test, https://outro.test']);

        $estrita = ContentSecurityPolicy::estrita();

        $this->assertStringContainsString('https://exemplo.test', $estrita);
        $this->assertStringContainsString('https://outro.test', $estrita);
        $this->assertStringNotContainsString('https://exemplo.test', ContentSecurityPolicy::base());
    }

    public function test_politica_estrita_libera_o_que_o_sistema_usa_hoje(): void
    {
        $estrita = ContentSecurityPolicy::estrita();

        foreach ([
            'https://www.google.com/recaptcha/', 'https://fonts.googleapis.com', 'https://fonts.gstatic.com',
            'https://cdn.tailwindcss.com', 'https://viacep.com.br', 'https://ui-avatars.com',
        ] as $origem) {
            $this->assertStringContainsString($origem, $estrita);
        }
    }

    public function test_resposta_que_define_a_propria_csp_nao_e_sobrescrita(): void
    {
        config(['seguranca.csp.modo' => 'enforce']);

        $this->actingAs(User::create(['name' => 'S', 'email' => 's@teste.com', 'password' => bcrypt('x')]));
        $this->seed(RolesSeeder::class);
        auth()->user()->assignRole('secretaria');
        Storage::fake('local');
        Storage::disk('local')->put('materiais-aula/x.html', '<script>1</script>');

        $csp = (string) $this->get('/visualizar-documento/materiais-aula/x.html')->headers->get('Content-Security-Policy');

        $this->assertSame("default-src 'none'; sandbox", $csp);
    }

    public function test_endpoint_recebe_relato_no_formato_report_uri_sem_gravar_dados_pessoais(): void
    {
        Log::spy();
        Cache::flush();

        $this->postJson('/api/csp-report', ['csp-report' => [
            'document-uri' => 'https://escola.test/admissao/TOKEN-SECRETO-DA-FAMILIA-1234567890?x=1',
            'effective-directive' => 'script-src-elem',
            'blocked-uri' => 'https://cdn.estranho.test/a/b/c.js?chave=123',
        ]], ['CONTENT_TYPE' => 'application/csp-report'])->assertNoContent();

        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $mensagem, array $contexto): bool {
            return $mensagem === 'CSP: violação relatada'
                && $contexto === ['diretiva' => 'script-src-elem', 'bloqueado' => 'https://cdn.estranho.test', 'pagina' => '/admissao'];
        });
    }

    public function test_endpoint_aceita_o_formato_da_reporting_api_e_deduplica(): void
    {
        Log::spy();
        Cache::flush();

        $relato = [['type' => 'csp-violation', 'body' => [
            'documentURL' => 'https://escola.test/admin/pessoas',
            'effectiveDirective' => 'img-src',
            'blockedURL' => 'inline',
        ]]];

        $this->postJson('/api/csp-report', $relato)->assertNoContent();
        $this->postJson('/api/csp-report', $relato)->assertNoContent();

        Log::shouldHaveReceived('warning')->once();
    }

    public function test_endpoint_ignora_lixo_e_recusa_corpo_enorme(): void
    {
        Log::spy();

        $this->call('POST', '/api/csp-report', [], [], [], ['CONTENT_TYPE' => 'application/json'], 'isso não é json')->assertNoContent();
        $this->call('POST', '/api/csp-report', [], [], [], ['CONTENT_TYPE' => 'application/json'], str_repeat('a', 20000))->assertStatus(413);

        Log::shouldNotHaveReceived('warning');
    }
}
