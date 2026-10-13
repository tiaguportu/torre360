<?php

namespace Tests\Feature;

use App\Services\ContractTemplateService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContratoImagensSsrfLoteHTest extends TestCase
{
    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://escola.test']);
        Storage::fake('public');
        Storage::fake('local');
        Storage::disk('public')->put('instituicao-logos/logo.png', base64_decode(self::PNG_1X1));
        Storage::disk('public')->put('logo.png', base64_decode(self::PNG_1X1));
    }

    private function processar(string $src): string
    {
        return app(ContractTemplateService::class)->processHtmlImages(sprintf('<img src="%s">', $src));
    }

    public function test_caminho_relativo_storage_embutido_com_sucesso(): void
    {
        $res = $this->processar('/storage/instituicao-logos/logo.png');
        $this->assertStringContainsString('data:image/png;base64,', $res);
    }

    public function test_url_absoluta_escola_test_embutida_com_sucesso(): void
    {
        $res = $this->processar('http://escola.test/storage/instituicao-logos/logo.png');
        $this->assertStringContainsString('data:image/png;base64,', $res);
    }

    public function test_url_absoluta_host_maiusculo_embutida_com_sucesso(): void
    {
        $res = $this->processar('http://ESCOLA.TEST/storage/instituicao-logos/logo.png');
        $this->assertStringContainsString('data:image/png;base64,', $res);
    }

    public function test_localhost_embutida_quando_app_url_e_localhost(): void
    {
        config(['app.url' => 'http://localhost']);
        $res = $this->processar('http://localhost/storage/instituicao-logos/logo.png');
        $this->assertStringContainsString('data:image/png;base64,', $res);
    }

    public function test_localhost_bloqueada_por_ssrf_quando_app_url_e_escola_test(): void
    {
        config(['app.url' => 'http://escola.test']);
        $res = $this->processar('http://localhost/storage/instituicao-logos/logo.png');
        $this->assertSame('<!-- [IMAGEM REMOTA BLOQUEADA CONTRA SSRF] -->', $res);
    }

    public function test_cloud_metadata_ip_bloqueado_por_ssrf(): void
    {
        $res = $this->processar('http://169.254.169.254/latest/meta-data/storage/x.png');
        $this->assertSame('<!-- [IMAGEM REMOTA BLOQUEADA CONTRA SSRF] -->', $res);
    }

    public function test_loopback_com_porta_bloqueado_por_ssrf(): void
    {
        $res = $this->processar('http://127.0.0.1:8000/admin/storage/x.png');
        $this->assertSame('<!-- [IMAGEM REMOTA BLOQUEADA CONTRA SSRF] -->', $res);
    }

    public function test_rede_privada_bloqueada_por_ssrf(): void
    {
        $res = $this->processar('http://10.0.0.5/internal/storage/x.png');
        $this->assertSame('<!-- [IMAGEM REMOTA BLOQUEADA CONTRA SSRF] -->', $res);
    }

    public function test_subdominio_malicioso_nao_e_origem_propria_e_nao_embute_disco(): void
    {
        $res = $this->processar('http://escola.test.evil.com/storage/x.png');
        $this->assertStringNotContainsString('data:', $res);
    }

    public function test_userinfo_malicioso_nao_e_origem_propria_e_nao_embute_disco(): void
    {
        $res = $this->processar('http://escola.test@evil.com/storage/x.png');
        $this->assertStringNotContainsString('data:', $res);
    }

    public function test_protocol_relative_nao_tratado_como_relativo_e_sem_data(): void
    {
        $res = $this->processar('//evil.com/storage/x.png');
        $this->assertStringNotContainsString('data:', $res);
    }

    public function test_esquema_phar_bloqueado(): void
    {
        $res = $this->processar('phar:///tmp/evil.phar/storage/x.png');
        $this->assertSame('<!-- [ESQUEMA DE IMAGEM BLOQUEADO] -->', $res);
    }

    public function test_esquema_gopher_bloqueado(): void
    {
        $res = $this->processar('gopher://10.0.0.5:6379/storage/x');
        $this->assertSame('<!-- [ESQUEMA DE IMAGEM BLOQUEADO] -->', $res);
    }

    public function test_path_traversal_em_visualizar_documento_inalterado_e_sem_data(): void
    {
        $src = '/visualizar-documento/../../.env';
        $res = $this->processar($src);
        $this->assertStringNotContainsString('data:', $res);
        $this->assertSame(sprintf('<img src="%s">', $src), $res);
    }

    public function test_url_externa_com_storage_no_caminho_nao_embute_arquivo_local(): void
    {
        $res = $this->processar('http://evil.com/foo/storage/logo.png');
        $this->assertStringNotContainsString('data:', $res);
    }
}
