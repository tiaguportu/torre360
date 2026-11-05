<?php

namespace Tests\Feature;

use App\Models\CampanhaMarketing;
use App\Services\UtmTracker;
use App\Support\ArquivoPrivado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArquivoPrivadoETrackerUtmTest extends TestCase
{
    use RefreshDatabase;

    // ---------- ArquivoPrivado: o tipo vem do conteúdo, não do nome ----------

    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function test_pdf_e_gravado_na_pasta_com_extensao_do_tipo_detectado(): void
    {
        Storage::fake('local');
        $arquivo = UploadedFile::fake()->createWithContent('qualquer-nome.bin', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF");

        $caminho = ArquivoPrivado::guardarDocumento($arquivo, 'processos_funcionario/12', 'item-7-20261021090000');

        $this->assertSame('processos_funcionario/12/item-7-20261021090000.pdf', $caminho);
        Storage::disk('local')->assertExists($caminho);
    }

    public function test_imagem_png_e_gravada_como_png_mesmo_com_nome_de_pdf(): void
    {
        Storage::fake('local');
        $arquivo = UploadedFile::fake()->createWithContent('contrato.pdf', base64_decode(self::PNG_1X1));

        $caminho = ArquivoPrivado::guardarDocumento($arquivo, 'docs', 'foto');

        $this->assertSame('docs/foto.png', $caminho);
        Storage::disk('local')->assertExists('docs/foto.png');
    }

    public function test_pasta_com_barras_nas_pontas_e_normalizada(): void
    {
        Storage::fake('local');
        $arquivo = UploadedFile::fake()->createWithContent('a.pdf', "%PDF-1.4\n%%EOF");

        $this->assertSame('docs/sub/arquivo.pdf', ArquivoPrivado::guardarDocumento($arquivo, '/docs/sub/', 'arquivo'));
    }

    public function test_html_enviado_como_pdf_e_recusado_e_nada_e_gravado(): void
    {
        Storage::fake('local');
        $arquivo = UploadedFile::fake()->createWithContent('contrato.pdf', '<html><script>alert(1)</script></html>');

        try {
            ArquivoPrivado::guardarDocumento($arquivo, 'docs', 'contrato');
            $this->fail('deveria recusar HTML disfarçado de PDF');
        } catch (\DomainException $e) {
            $this->assertSame('Envie o arquivo em PDF ou em foto (JPG, PNG ou WEBP).', $e->getMessage());
        }

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_svg_e_script_sao_recusados(): void
    {
        Storage::fake('local');

        foreach ([
            'imagem.png' => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
            'foto.jpg' => "<?php echo 'x';",
        ] as $nome => $conteudo) {
            try {
                ArquivoPrivado::guardarDocumento(UploadedFile::fake()->createWithContent($nome, $conteudo), 'docs', 'x');
                $this->fail("{$nome} deveria ser recusado");
            } catch (\DomainException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_arquivo_vazio_e_recusado(): void
    {
        Storage::fake('local');
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Arquivo vazio ou ilegível.');

        ArquivoPrivado::guardarDocumento(UploadedFile::fake()->createWithContent('vazio.pdf', ''), 'docs', 'x');
    }

    // ---------- UtmTracker ----------

    private function requisicao(array $query = [], array $input = []): Request
    {
        $requisicao = Request::create('/matricular-online', 'POST', $input, [], [], [], '');
        $requisicao->query->replace($query);
        $requisicao->setLaravelSession($this->app['session.store']);

        return $requisicao;
    }

    public function test_captura_os_utm_da_query_normalizados_e_guarda_na_sessao(): void
    {
        $requisicao = $this->requisicao(['utm_source' => '  Google ', 'utm_medium' => 'CPC', 'utm_campaign' => 'Matriculas2027', 'outro' => 'x']);

        UtmTracker::capturar($requisicao);

        $this->assertSame(
            ['utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'matriculas2027'],
            $requisicao->session()->get('captacao_utm'),
        );
    }

    public function test_visita_sem_utm_nao_apaga_o_que_ja_estava_na_sessao(): void
    {
        $primeira = $this->requisicao(['utm_source' => 'instagram']);
        UtmTracker::capturar($primeira);

        UtmTracker::capturar($this->requisicao(['pagina' => '2']));

        $this->assertSame(['utm_source' => 'instagram'], $primeira->session()->get('captacao_utm'));
    }

    public function test_nova_visita_com_utm_sobrescreve_a_anterior(): void
    {
        $requisicao = $this->requisicao(['utm_source' => 'instagram', 'utm_medium' => 'social']);
        UtmTracker::capturar($requisicao);

        UtmTracker::capturar($this->requisicao(['utm_source' => 'google']));

        $this->assertSame(['utm_source' => 'google'], $requisicao->session()->get('captacao_utm'), 'last touch: não mistura com a visita anterior');
    }

    public function test_valores_vazios_ou_nao_texto_e_o_limite_de_191_caracteres(): void
    {
        $requisicao = $this->requisicao(['utm_source' => '   ', 'utm_medium' => ['a'], 'utm_campaign' => str_repeat('A', 300)]);

        UtmTracker::capturar($requisicao);

        $this->assertSame(['utm_campaign' => str_repeat('a', 191)], $requisicao->session()->get('captacao_utm'));
    }

    public function test_atribuicao_sem_nenhum_utm_e_vazia(): void
    {
        $this->assertSame([], UtmTracker::atribuicao($this->requisicao()));
    }

    public function test_utm_do_proprio_envio_vence_o_da_sessao(): void
    {
        $requisicao = $this->requisicao(['utm_source' => 'instagram', 'utm_medium' => 'social'], ['utm_source' => 'Indicacao']);
        UtmTracker::capturar($requisicao);

        $this->assertSame(
            ['utm_source' => 'indicacao', 'utm_medium' => 'social'],
            UtmTracker::atribuicao($requisicao),
        );
    }

    public function test_atribuicao_liga_a_campanha_ativa_pelo_codigo_utm(): void
    {
        $ativa = CampanhaMarketing::create(['nome' => 'Matrículas 2027', 'canal' => 'google_ads', 'codigo_utm' => 'matriculas2027', 'ativa' => true]);
        CampanhaMarketing::create(['nome' => 'Antiga', 'canal' => 'google_ads', 'codigo_utm' => 'antiga', 'ativa' => false]);

        $comAtiva = UtmTracker::atribuicao($this->requisicao([], ['utm_campaign' => 'Matriculas2027']));
        $comInativa = UtmTracker::atribuicao($this->requisicao([], ['utm_campaign' => 'antiga']));
        $semCampanha = UtmTracker::atribuicao($this->requisicao([], ['utm_campaign' => 'inexistente']));

        $this->assertSame($ativa->id, $comAtiva['campanha_marketing_id']);
        $this->assertArrayNotHasKey('campanha_marketing_id', $comInativa, 'campanha desativada não recebe lead');
        $this->assertArrayNotHasKey('campanha_marketing_id', $semCampanha);
        $this->assertSame('inexistente', $semCampanha['utm_campaign']);
    }
}
