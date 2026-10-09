<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ContractTemplateService;
use App\Support\TiposArquivo;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Arquivos enviados por usuários são servidos na mesma origem do painel: HTML/SVG enviado por um perfil de baixo
 * privilégio não pode rodar script na sessão de quem o abrir.
 */
class UploadsSegurosTest extends TestCase
{
    use RefreshDatabase;

    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        Storage::fake('local');

        $secretaria = User::create(['name' => 'Secretaria', 'email' => 'secretaria@teste.com', 'password' => bcrypt('x')]);
        $secretaria->assignRole('secretaria');
        $this->actingAs($secretaria);
    }

    private function guardar(string $caminho, string $conteudo): string
    {
        Storage::disk('local')->put($caminho, $conteudo);

        return '/visualizar-documento/'.$caminho;
    }

    public function test_html_enviado_baixa_como_anexo_isolado_e_nunca_abre_na_pagina(): void
    {
        $url = $this->guardar('materiais-aula/prova.html', '<html><script>fetch("/admin")</script></html>');

        $resposta = $this->get($url)->assertOk();

        $this->assertStringStartsWith('attachment', (string) $resposta->headers->get('Content-Disposition'));
        $this->assertStringContainsString('sandbox', (string) $resposta->headers->get('Content-Security-Policy'));
        $this->assertSame('application/octet-stream', $resposta->headers->get('Content-Type'));
        $this->assertSame('nosniff', $resposta->headers->get('X-Content-Type-Options'));
    }

    public function test_svg_com_script_tambem_baixa_como_anexo(): void
    {
        $url = $this->guardar('materiais-aula/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $resposta = $this->get($url)->assertOk();

        $this->assertStringStartsWith('attachment', (string) $resposta->headers->get('Content-Disposition'));
        $this->assertStringContainsString('sandbox', (string) $resposta->headers->get('Content-Security-Policy'));
    }

    public function test_html_com_extensao_de_imagem_ou_pdf_e_detectado_pelo_conteudo(): void
    {
        foreach (['planos-aula/foto.png', 'planos-aula/documento.pdf'] as $caminho) {
            $url = $this->guardar($caminho, '<!DOCTYPE html><html><body><script>alert(1)</script></body></html>');

            $resposta = $this->get($url)->assertOk();

            $this->assertStringStartsWith('attachment', (string) $resposta->headers->get('Content-Disposition'), $caminho);
        }
    }

    public function test_pdf_e_imagem_verdadeiros_continuam_abrindo_na_pagina(): void
    {
        $pdf = $this->get($this->guardar('materiais-aula/apostila.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF"))->assertOk();
        $this->assertStringStartsWith('inline', (string) $pdf->headers->get('Content-Disposition'));
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertSame("frame-ancestors 'self'", $pdf->headers->get('Content-Security-Policy'));

        $png = $this->get($this->guardar('pessoas_fotos/aluno.png', base64_decode(self::PNG_1X1)))->assertOk();
        $this->assertStringStartsWith('inline', (string) $png->headers->get('Content-Disposition'));
        $this->assertSame('image/png', $png->headers->get('Content-Type'));
    }

    public function test_documento_privado_nao_vai_para_cache_publico(): void
    {
        $url = $this->guardar('materiais-aula/apostila.pdf', "%PDF-1.4\n%%EOF");

        $cacheControl = (string) $this->get($url)->assertOk()->headers->get('Cache-Control');

        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringNotContainsString('public', $cacheControl);
    }

    public function test_listas_de_tipos_nao_aceitam_html_nem_svg(): void
    {
        foreach ([TiposArquivo::imagens(), TiposArquivo::documentos(), TiposArquivo::materiaisDeAula()] as $lista) {
            $this->assertNotContains('image/svg+xml', $lista);
            $this->assertNotContains('text/html', $lista);
            $this->assertNotContains('application/xhtml+xml', $lista);
            $this->assertNotContains('text/xml', $lista);
            $this->assertNotContains('image/*', $lista);
        }
    }

    public function test_regra_mimetypes_das_listas_recusa_html_e_aceita_pdf(): void
    {
        $regra = 'mimetypes:'.implode(',', TiposArquivo::materiaisDeAula());

        $html = UploadedFile::fake()->createWithContent('aula.html', '<html><script>alert(1)</script></html>');
        $pdf = UploadedFile::fake()->createWithContent('aula.pdf', "%PDF-1.4\n%%EOF");

        $this->assertTrue(Validator::make(['f' => $html], ['f' => $regra])->fails());
        $this->assertFalse(Validator::make(['f' => $pdf], ['f' => $regra])->fails());
    }

    /**
     * Guarda para o futuro: todo FileUpload do painel precisa declarar o que aceita, e `image/*` (que inclui SVG) é proibido.
     */
    public function test_todo_file_upload_declara_tipos_e_nenhum_usa_image_curinga(): void
    {
        // Importação de extrato bancário (OFX/CSV): lida e processada no servidor, nunca servida de volta ao navegador.
        $isentos = ['TransacaoBancarias/Pages/ListTransacaoBancarias.php'];

        $semTipos = [];
        $comCuringa = [];

        foreach (File::allFiles(app_path('Filament')) as $arquivo) {
            $conteudo = $arquivo->getContents();
            $relativo = str_replace('\\', '/', $arquivo->getRelativePathname());

            if (str_contains($conteudo, "'image/*'")) {
                $comCuringa[] = $relativo;
            }

            if (collect($isentos)->contains(fn (string $isento) => str_ends_with($relativo, $isento))) {
                continue;
            }

            $uploads = substr_count($conteudo, 'FileUpload::make(');

            if ($uploads > 0 && substr_count($conteudo, 'acceptedFileTypes(') < $uploads) {
                $semTipos[] = $relativo;
            }
        }

        $this->assertSame([], $comCuringa, "Use TiposArquivo em vez de 'image/*' (inclui SVG).");
        $this->assertSame([], $semTipos, 'FileUpload sem acceptedFileTypes(): declare a lista branca (App\\Support\\TiposArquivo).');
    }

    public function test_imagem_de_contrato_nao_sai_das_pastas_de_arquivos_do_sistema(): void
    {
        $env = base_path('.env');
        $criouEnv = ! file_exists($env);

        if ($criouEnv) {
            file_put_contents($env, 'CHAVE_SECRETA=nao-pode-vazar');
        }

        try {
            $html = '<p><img src="/visualizar-documento/../../.env"></p>';
            $resultado = app(ContractTemplateService::class)->processHtmlImages($html);

            $this->assertStringNotContainsString('data:', $resultado, 'o .env não pode virar imagem base64 dentro do PDF');
            $this->assertSame($html, $resultado);
        } finally {
            if ($criouEnv) {
                unlink($env);
            }
        }
    }

    public function test_imagem_legitima_do_disco_publico_continua_sendo_embutida(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('instituicao-logos/logo.png', base64_decode(self::PNG_1X1));

        $resultado = app(ContractTemplateService::class)->processHtmlImages('<img src="http://escola.test/storage/instituicao-logos/logo.png">');

        $this->assertStringContainsString('data:image/png;base64,', $resultado);
    }

    public function test_dompdf_nao_enxerga_o_projeto_inteiro(): void
    {
        $chroot = (array) config('dompdf.options.chroot');

        $this->assertNotEmpty($chroot);
        $this->assertNotContains(realpath(base_path()), $chroot);

        foreach ($chroot as $pasta) {
            $this->assertStringNotContainsString('.env', $pasta);
            $this->assertFalse(file_exists($pasta.DIRECTORY_SEPARATOR.'.env'), "$pasta não deveria conter o .env");
        }
    }
}
