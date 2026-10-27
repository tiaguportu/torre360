<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\QrCodeService;
use Tests\TestCase;

class QrCodeServiceTest extends TestCase
{
    private QrCodeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new QrCodeService;
    }

    public function test_pode_ser_instanciado_e_resolvido_pelo_container(): void
    {
        $service = app(QrCodeService::class);

        $this->assertInstanceOf(QrCodeService::class, $service);
    }

    public function test_render_svg_gera_markup_svg_valido(): void
    {
        $dados = 'https://torre360.com.br/validar/DOC-12345';
        $svg = $this->service->renderSvg($dados);

        $this->assertIsString($svg);
        $this->assertNotEmpty($svg);
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('</svg>', $svg);
        $this->assertStringContainsString('xmlns="http://www.w3.org/2000/svg"', $svg);
        $this->assertStringContainsString('viewBox=', $svg);
    }

    public function test_render_data_uri_gera_uri_base64_valida(): void
    {
        $dados = 'https://torre360.com.br/validar/DOC-12345';
        $dataUri = $this->service->renderDataUri($dados);

        $prefixo = 'data:image/svg+xml;base64,';

        $this->assertIsString($dataUri);
        $this->assertStringStartsWith($prefixo, $dataUri);

        $base64Payload = substr($dataUri, strlen($prefixo));
        $svgDecodificado = base64_decode($base64Payload, true);

        $this->assertNotFalse($svgDecodificado, 'O payload da Data URI deve ser um base64 válido.');
        $this->assertStringContainsString('<svg', $svgDecodificado);
        $this->assertStringContainsString('</svg>', $svgDecodificado);
        $this->assertStringContainsString('xmlns="http://www.w3.org/2000/svg"', $svgDecodificado);
    }

    public function test_renderiza_corretamente_diferentes_tipos_de_dados(): void
    {
        $cenarios = [
            'url_simples' => 'https://exemplo.com',
            'uuid' => 'a8098c1a-f86e-11da-bd1a-00112444be1e',
            'texto_com_acentos_e_especiais' => 'Declaração Escolar: João & Maria - Nº 100/2026',
            'json' => '{"id":123,"hash":"abcde"}',
            'conteudo_longo' => str_repeat('TORRE360-VALIDACAO-', 10),
        ];

        foreach ($cenarios as $descricao => $conteudo) {
            $svg = $this->service->renderSvg($conteudo);
            $this->assertStringContainsString('<svg', $svg, "Falha ao gerar SVG para o cenário: {$descricao}");
            $this->assertStringContainsString('</svg>', $svg, "Falha na tag de fechamento para o cenário: {$descricao}");

            $dataUri = $this->service->renderDataUri($conteudo);
            $this->assertStringStartsWith('data:image/svg+xml;base64,', $dataUri, "Falha ao gerar Data URI para o cenário: {$descricao}");
        }
    }
}
