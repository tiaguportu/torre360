<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Livros\Pages\CreateLivro;
use App\Models\Livro;
use App\Models\User;
use App\Services\LivroCapaService;
use App\Services\LivroLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Busca de livro por ISBN: cache, fontes em paralelo, Amazon opt-in, validação da imagem da capa, proteção contra
 * SSRF e ciclo de vida do arquivo da capa (pendente -> definitivo -> descartado).
 */
class LivroLookupCapaTest extends TestCase
{
    use RefreshDatabase;

    private const ISBN = '9788576082675';

    private const URL_CAPA = 'https://covers.openlibrary.org/b/id/777-L.jpg';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Http::preventStrayRequests();
        config(['services.livros.validar_dns' => false]);
    }

    private function imagem(int $largura = 120, int $altura = 180, string $formato = 'png'): string
    {
        $imagem = imagecreatetruecolor($largura, $altura);
        imagefill($imagem, 0, 0, imagecolorallocate($imagem, 30, 90, 160));

        ob_start();
        $formato === 'jpeg' ? imagejpeg($imagem) : imagepng($imagem);

        return (string) ob_get_clean();
    }

    /**
     * @param  array<string, mixed>  $extras
     */
    private function fakeHttp(array $extras = [], ?string $capaUrl = self::URL_CAPA, mixed $corpoCapa = null, string $tipoCapa = 'image/png'): void
    {
        $livro = [
            'title' => 'Código Limpo',
            'authors' => [['name' => 'Robert C. Martin']],
            'publishers' => [['name' => 'Alta Books']],
            'subjects' => [['name' => 'Engenharia de Software']],
        ];

        if ($capaUrl !== null) {
            $livro['cover'] = ['large' => $capaUrl];
        }

        $stubs = $extras + [
            'https://openlibrary.org/api/books*' => Http::response(['ISBN:'.self::ISBN => $livro], 200),
        ];

        if ($capaUrl !== null) {
            $stubs[$capaUrl] = Http::response($corpoCapa ?? $this->imagem(), 200, ['Content-Type' => $tipoCapa]);
        }

        Http::fake($stubs + ['*' => Http::response('', 404)]);
    }

    private function envios(): int
    {
        return Http::recorded()->count();
    }

    private function enviosPara(string $trecho): int
    {
        return Http::recorded()->filter(fn (array $par): bool => str_contains($par[0]->url(), $trecho))->count();
    }

    /**
     * @return list<string>
     */
    private function pendentes(): array
    {
        return Storage::disk('public')->files(LivroLookupService::DIRETORIO_PENDENTES);
    }

    private function servico(): LivroLookupService
    {
        return app(LivroLookupService::class);
    }

    // ------------------------------------------------------------------ cache

    public function test_repetir_a_busca_do_mesmo_isbn_nao_chama_as_fontes_nem_cria_outro_arquivo(): void
    {
        $this->fakeHttp();

        $primeira = $this->servico()->buscarPorIsbn(self::ISBN);
        $enviosDaPrimeira = $this->envios();

        $segunda = $this->servico()->buscarPorIsbn(self::ISBN);
        $terceira = $this->servico()->buscarPorIsbn('978-85-7608-267-5');

        $this->assertTrue($primeira['sucesso']);
        $this->assertSame($enviosDaPrimeira, $this->envios(), 'Buscas repetidas não podem sair para a internet.');
        $this->assertSame($primeira['dados']['capa'], $segunda['dados']['capa']);
        $this->assertSame($primeira['dados']['capa'], $terceira['dados']['capa']);
        $this->assertSame('Código Limpo', $segunda['dados']['titulo']);
        $this->assertCount(1, $this->pendentes(), 'Cada clique gravava uma capa nova; agora é um único arquivo por ISBN.');
    }

    public function test_isbn_nao_encontrado_e_cacheado_por_uma_hora(): void
    {
        Http::fake(['*' => Http::response('', 404)]);

        $this->assertFalse($this->servico()->buscarPorIsbn(self::ISBN)['sucesso']);
        $envios = $this->envios();
        $this->assertGreaterThan(0, $envios);

        $this->assertFalse($this->servico()->buscarPorIsbn(self::ISBN)['sucesso']);
        $this->assertSame($envios, $this->envios());

        $this->travel(61)->minutes();
        $this->servico()->buscarPorIsbn(self::ISBN);
        $this->assertGreaterThan($envios, $this->envios(), 'Passada 1 hora o ISBN é consultado de novo.');
    }

    public function test_fonte_fora_do_ar_nao_vira_nao_encontrado_em_cache(): void
    {
        Http::fake([
            'https://openlibrary.org/*' => Http::response('erro', 503),
            '*' => Http::response('', 404),
        ]);

        $resultado = $this->servico()->buscarPorIsbn(self::ISBN);

        $this->assertFalse($resultado['sucesso']);
        $this->assertStringContainsString('indisponível', $resultado['mensagem']);
        $this->assertStringNotContainsString('Nenhum dado encontrado', $resultado['mensagem']);

        $envios = $this->envios();
        $this->servico()->buscarPorIsbn(self::ISBN);
        $this->assertGreaterThan($envios, $this->envios(), 'Falha de rede não pode ser guardada como "o livro não existe".');
    }

    public function test_falha_de_conexao_tambem_nao_e_cacheada(): void
    {
        Http::fake([
            'https://openlibrary.org/*' => fn () => throw new ConnectionException('cURL error 28: timeout'),
            '*' => Http::response('', 404),
        ]);

        $resultado = $this->servico()->buscarPorIsbn(self::ISBN);

        $this->assertFalse($resultado['sucesso']);
        $this->assertStringContainsString('indisponível', $resultado['mensagem']);

        $envios = $this->envios();
        $this->servico()->buscarPorIsbn(self::ISBN);
        $this->assertGreaterThan($envios, $this->envios());
    }

    public function test_resultado_parcial_por_falha_de_uma_fonte_tem_cache_curto(): void
    {
        Http::fake([
            'https://openlibrary.org/*' => Http::response('erro', 500),
            'https://brasilapi.com.br/*' => Http::response(['title' => 'Dom Casmurro', 'authors' => []], 200),
            '*' => Http::response('', 404),
        ]);

        $this->assertTrue($this->servico()->buscarPorIsbn(self::ISBN)['sucesso']);
        $envios = $this->envios();

        $this->servico()->buscarPorIsbn(self::ISBN);
        $this->assertSame($envios, $this->envios());

        // 10 minutos: dá a chance de a fonte que falhou completar autor/editora.
        $this->travel(11)->minutes();
        $this->servico()->buscarPorIsbn(self::ISBN);
        $this->assertGreaterThan($envios, $this->envios());
    }

    public function test_quando_nenhuma_fonte_tem_capa_cliques_seguidos_nao_repetem_a_tentativa(): void
    {
        $this->fakeHttp(capaUrl: null);

        $this->servico()->buscarPorIsbn(self::ISBN);
        $this->assertSame(1, $this->enviosPara('covers.openlibrary.org'));

        $this->servico()->buscarPorIsbn(self::ISBN);
        $this->servico()->buscarPorIsbn(self::ISBN);
        $this->assertSame(1, $this->enviosPara('covers.openlibrary.org'));

        $this->travel(16)->minutes();
        $this->servico()->buscarPorIsbn(self::ISBN);
        $this->assertSame(2, $this->enviosPara('covers.openlibrary.org'), 'Depois de 15 minutos tenta de novo.');
    }

    // ------------------------------------------------------------------ fontes

    public function test_as_tres_fontes_publicas_sao_consultadas_na_mesma_busca(): void
    {
        $this->fakeHttp(capaUrl: null);

        $this->servico()->buscarPorIsbn(self::ISBN);

        $this->assertSame(1, $this->enviosPara('openlibrary.org/api/books'));
        $this->assertSame(1, $this->enviosPara('brasilapi.com.br'));
        $this->assertSame(1, $this->enviosPara('googleapis.com/books'));
    }

    public function test_google_books_usa_a_chave_do_config(): void
    {
        config(['services.google_books.key' => 'CHAVE-DE-TESTE']);
        $this->fakeHttp(capaUrl: null);

        $this->servico()->buscarPorIsbn(self::ISBN);

        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'googleapis.com/books') && str_contains($r->url(), 'key=CHAVE-DE-TESTE'));
    }

    public function test_google_books_sem_chave_nao_envia_o_parametro(): void
    {
        config(['services.google_books.key' => null]);
        $this->fakeHttp(capaUrl: null);

        $this->servico()->buscarPorIsbn(self::ISBN);

        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'googleapis.com/books') && ! str_contains($r->url(), 'key='));
    }

    public function test_a_chave_do_google_books_existe_no_arquivo_de_config(): void
    {
        $this->assertArrayHasKey('key', config('services.google_books'));
        $this->assertArrayHasKey('amazon_habilitado', config('services.livros'));
    }

    public function test_google_books_tenta_o_isbn_10_quando_nao_acha_pelo_13(): void
    {
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => function (Request $request) {
                return str_contains(urldecode($request->url()), 'isbn:8576082675')
                    ? Http::response(['items' => [['volumeInfo' => ['title' => 'Só no ISBN-10', 'authors' => ['Fulano']]]]], 200)
                    : Http::response(['totalItems' => 0], 200);
            },
            '*' => Http::response('', 404),
        ]);

        $resultado = $this->servico()->buscarPorIsbn(self::ISBN);

        $this->assertTrue($resultado['sucesso']);
        $this->assertSame('Só no ISBN-10', $resultado['dados']['titulo']);
    }

    public function test_prioridade_entre_fontes_open_library_depois_brasilapi_depois_google(): void
    {
        Http::fake([
            'https://openlibrary.org/*' => Http::response(['ISBN:'.self::ISBN => ['title' => 'Título OL']], 200),
            'https://brasilapi.com.br/*' => Http::response(['title' => 'Título Brasil', 'authors' => ['Autor Brasil'], 'publisher' => 'Editora Brasil'], 200),
            'https://www.googleapis.com/*' => Http::response(['items' => [['volumeInfo' => ['title' => 'Título Google', 'authors' => ['Autor Google'], 'publisher' => 'Editora Google', 'categories' => ['Categoria Google']]]]], 200),
            '*' => Http::response('', 404),
        ]);

        $dados = $this->servico()->buscarPorIsbn(self::ISBN)['dados'];

        $this->assertSame('Título OL', $dados['titulo']);
        $this->assertSame('Autor Brasil', $dados['autor']);
        $this->assertSame('Editora Brasil', $dados['editora']);
        $this->assertSame('Categoria Google', $dados['categoria']);
    }

    // ------------------------------------------------------------------ Amazon (opt-in)

    private function fakeSoEditora(): void
    {
        Http::fake([
            'https://brasilapi.com.br/*' => Http::response(['title' => 'Obra sem autor', 'authors' => [], 'publisher' => 'Editora X'], 200),
            'https://www.amazon.com.br/*' => Http::response('<span id="productTitle">Obra</span><title>Obra: Autor Amazon: Amazon.com.br</title>', 200),
            '*' => Http::response('', 404),
        ]);
    }

    public function test_amazon_nao_e_consultada_por_padrao(): void
    {
        $this->fakeSoEditora();

        $resultado = $this->servico()->buscarPorIsbn(self::ISBN);

        $this->assertTrue($resultado['sucesso']);
        $this->assertNull($resultado['dados']['autor']);
        $this->assertSame(0, $this->enviosPara('amazon'));
        $this->assertSame(0, $this->enviosPara('ssl-images-amazon'));
    }

    public function test_amazon_so_e_consultada_quando_habilitada(): void
    {
        config(['services.livros.amazon_habilitado' => true]);
        $this->fakeSoEditora();

        $this->servico()->buscarPorIsbn(self::ISBN);

        $this->assertSame(1, $this->enviosPara('www.amazon.com.br'));
    }

    // ------------------------------------------------------------------ validação da imagem

    public function test_corpo_que_nao_e_imagem_nao_vira_capa_mesmo_com_content_type_de_imagem(): void
    {
        $this->fakeHttp(corpoCapa: '<html><body>Verificação de robô</body></html>', tipoCapa: 'image/jpeg');

        $resultado = $this->servico()->buscarPorIsbn(self::ISBN);

        $this->assertTrue($resultado['sucesso']);
        $this->assertNull($resultado['dados']['capa']);
        $this->assertSame([], $this->pendentes());
    }

    public function test_placeholder_de_um_pixel_e_recusado(): void
    {
        $this->fakeHttp(corpoCapa: $this->imagem(1, 1));

        $this->assertNull($this->servico()->buscarPorIsbn(self::ISBN)['dados']['capa']);
        $this->assertSame([], $this->pendentes());
    }

    public function test_imagem_acima_do_limite_de_tamanho_e_recusada(): void
    {
        $this->fakeHttp(corpoCapa: $this->imagem().str_repeat("\0", LivroLookupService::MAX_BYTES_CAPA + 1));

        $this->assertNull($this->servico()->buscarPorIsbn(self::ISBN)['dados']['capa']);
        $this->assertSame([], $this->pendentes());
    }

    public function test_extensao_vem_do_conteudo_e_nao_do_cabecalho(): void
    {
        // O servidor diz que é JPEG, mas os bytes são PNG.
        $this->fakeHttp(corpoCapa: $this->imagem(), tipoCapa: 'image/jpeg');

        $capa = $this->servico()->buscarPorIsbn(self::ISBN)['dados']['capa'];

        $this->assertSame(LivroLookupService::DIRETORIO_PENDENTES.'/'.self::ISBN.'.png', $capa);
        Storage::disk('public')->assertExists($capa);
    }

    public function test_formato_nao_permitido_e_recusado(): void
    {
        $gif = imagecreatetruecolor(100, 100);
        ob_start();
        imagegif($gif);
        $this->fakeHttp(corpoCapa: (string) ob_get_clean(), tipoCapa: 'image/gif');

        $this->assertNull($this->servico()->buscarPorIsbn(self::ISBN)['dados']['capa']);
    }

    public function test_se_a_capa_da_fonte_e_invalida_usa_a_do_open_library_covers(): void
    {
        $this->fakeHttp(
            extras: ['https://covers.openlibrary.org/b/isbn/'.self::ISBN.'-L.jpg*' => Http::response($this->imagem(200, 300, 'jpeg'), 200, ['Content-Type' => 'image/jpeg'])],
            corpoCapa: 'não sou imagem',
        );

        $resultado = $this->servico()->buscarPorIsbn(self::ISBN);

        $this->assertSame(LivroLookupService::DIRETORIO_PENDENTES.'/'.self::ISBN.'.jpg', $resultado['dados']['capa']);
        $this->assertStringContainsString('covers.openlibrary.org/b/isbn/', (string) $resultado['dados']['capa_url']);
    }

    // ------------------------------------------------------------------ SSRF

    /**
     * @return array<string, array{0: string}>
     */
    public static function urlsInternas(): array
    {
        return [
            'loopback' => ['http://127.0.0.1/capa.jpg'],
            'localhost' => ['http://localhost/capa.jpg'],
            'metadados de nuvem' => ['http://169.254.169.254/latest/meta-data/'],
            'rede privada' => ['http://192.168.0.10/capa.jpg'],
            'rede privada 10.x' => ['https://10.0.0.5/capa.jpg'],
            'ipv6 loopback' => ['http://[::1]/capa.jpg'],
            'porta incomum' => ['https://exemplo.com:8443/capa.jpg'],
            'esquema ftp' => ['ftp://exemplo.com/capa.jpg'],
            'esquema file' => ['file:///etc/passwd'],
        ];
    }

    #[DataProvider('urlsInternas')]
    public function test_capa_so_e_baixada_de_endereco_publico(string $urlCapa): void
    {
        $this->fakeHttp(capaUrl: null, extras: [
            'https://brasilapi.com.br/*' => Http::response(['title' => 'Obra', 'authors' => ['Autor'], 'cover_url' => $urlCapa], 200),
        ]);

        $resultado = $this->servico()->buscarPorIsbn(self::ISBN);

        $this->assertTrue($resultado['sucesso']);
        $this->assertNull($resultado['dados']['capa']);
        $this->assertSame(0, Http::recorded()->filter(fn (array $par): bool => $par[0]->url() === $urlCapa)->count(), 'A URL interna não pode ser requisitada.');
    }

    public function test_com_validacao_de_dns_ligada_localhost_e_recusado(): void
    {
        config(['services.livros.validar_dns' => true]);
        $this->fakeHttp(capaUrl: null, extras: [
            'https://brasilapi.com.br/*' => Http::response(['title' => 'Obra', 'authors' => ['Autor'], 'cover_url' => 'http://localhost/capa.jpg'], 200),
        ]);

        $this->assertNull($this->servico()->buscarPorIsbn(self::ISBN)['dados']['capa']);
        $this->assertSame(0, $this->enviosPara('localhost'));
    }

    // ------------------------------------------------------------------ capa pendente e ciclo de vida

    public function test_capa_pendente_reutilizada_tem_a_data_renovada_para_nao_ser_limpa_com_o_formulario_aberto(): void
    {
        $this->fakeHttp();
        $capa = $this->servico()->buscarPorIsbn(self::ISBN)['dados']['capa'];

        $caminho = Storage::disk('public')->path($capa);
        touch($caminho, now()->subHours(30)->getTimestamp());
        clearstatcache();

        $this->servico()->buscarPorIsbn(self::ISBN);
        clearstatcache();

        $this->assertGreaterThan(now()->subHour()->getTimestamp(), Storage::disk('public')->lastModified($capa));
    }

    private function livroComCapa(?string $capa, string $isbn = self::ISBN): Livro
    {
        return Livro::create([
            'titulo' => 'Código Limpo',
            'autor' => 'Robert C. Martin',
            'isbn' => $isbn,
            'quantidade_total' => 1,
            'quantidade_disponivel' => 1,
            'capa' => $capa,
        ]);
    }

    public function test_ao_salvar_o_livro_a_capa_pendente_vira_um_arquivo_definitivo(): void
    {
        $this->fakeHttp();
        $pendente = $this->servico()->buscarPorIsbn(self::ISBN)['dados']['capa'];

        $livro = $this->livroComCapa($pendente);

        $this->assertStringStartsWith('livros/capas/', $livro->capa);
        $this->assertStringNotContainsString('pendentes', $livro->capa);
        $this->assertStringStartsWith('livros/capas/'.self::ISBN.'_', $livro->capa);
        Storage::disk('public')->assertExists($livro->capa);
        // O pendente continua lá para outros formulários abertos com o mesmo ISBN; a limpeza diária cuida dele.
        Storage::disk('public')->assertExists($pendente);
        $this->assertSame($livro->capa, $livro->fresh()->capa);
    }

    public function test_dois_livros_com_o_mesmo_isbn_ganham_arquivos_proprios_e_apagar_um_nao_quebra_o_outro(): void
    {
        $this->fakeHttp();
        $pendente = $this->servico()->buscarPorIsbn(self::ISBN)['dados']['capa'];

        $a = $this->livroComCapa($pendente);
        $b = $this->livroComCapa($pendente);

        $this->assertNotSame($a->capa, $b->capa);

        $a->delete();

        Storage::disk('public')->assertMissing($a->capa);
        Storage::disk('public')->assertExists($b->fresh()->capa);
    }

    public function test_trocar_a_capa_apaga_o_arquivo_antigo(): void
    {
        $this->fakeHttp();
        $livro = $this->livroComCapa($this->servico()->buscarPorIsbn(self::ISBN)['dados']['capa']);
        $antiga = $livro->capa;

        Storage::disk('public')->put('livros/capas/nova.png', $this->imagem());
        $livro->update(['capa' => 'livros/capas/nova.png']);

        Storage::disk('public')->assertMissing($antiga);
        Storage::disk('public')->assertExists('livros/capas/nova.png');
    }

    public function test_arquivo_usado_por_outro_livro_nao_e_apagado(): void
    {
        Storage::disk('public')->put('livros/capas/compartilhada.png', $this->imagem());
        $a = $this->livroComCapa('livros/capas/compartilhada.png', '9780000000001');
        $b = $this->livroComCapa('livros/capas/compartilhada.png', '9780000000002');

        $a->delete();

        Storage::disk('public')->assertExists('livros/capas/compartilhada.png');

        $b->delete();

        Storage::disk('public')->assertMissing('livros/capas/compartilhada.png');
    }

    public function test_capa_remota_ou_vazia_nao_mexe_no_disco(): void
    {
        Storage::disk('public')->put('livros/capas/outra.png', $this->imagem());
        $remota = $this->livroComCapa('https://exemplo.com/capa.jpg', '9780000000003');
        $sem = $this->livroComCapa(null, '9780000000004');

        $remota->delete();
        $sem->delete();

        Storage::disk('public')->assertExists('livros/capas/outra.png');
    }

    public function test_pendente_que_ja_foi_limpo_zera_a_referencia_em_vez_de_apontar_para_arquivo_inexistente(): void
    {
        $livro = $this->livroComCapa(LivroLookupService::DIRETORIO_PENDENTES.'/'.self::ISBN.'.png');

        $this->assertNull($livro->fresh()->capa);
    }

    public function test_comando_limpa_somente_pendentes_antigos(): void
    {
        $disco = Storage::disk('public');
        $disco->put(LivroLookupService::DIRETORIO_PENDENTES.'/velha.png', $this->imagem());
        $disco->put(LivroLookupService::DIRETORIO_PENDENTES.'/recente.png', $this->imagem());
        $disco->put('livros/capas/definitiva.png', $this->imagem());
        touch($disco->path(LivroLookupService::DIRETORIO_PENDENTES.'/velha.png'), now()->subHours(60)->getTimestamp());
        clearstatcache();

        $this->artisan('biblioteca:limpar-capas-pendentes')
            ->expectsOutputToContain('1 capa(s) pendente(s) removida(s).')
            ->assertSuccessful();

        $disco->assertMissing(LivroLookupService::DIRETORIO_PENDENTES.'/velha.png');
        $disco->assertExists(LivroLookupService::DIRETORIO_PENDENTES.'/recente.png');
        $disco->assertExists('livros/capas/definitiva.png');
    }

    public function test_comando_respeita_o_prazo_informado(): void
    {
        $disco = Storage::disk('public');
        $disco->put(LivroLookupService::DIRETORIO_PENDENTES.'/meio.png', $this->imagem());
        touch($disco->path(LivroLookupService::DIRETORIO_PENDENTES.'/meio.png'), now()->subHours(5)->getTimestamp());
        clearstatcache();

        $this->artisan('biblioteca:limpar-capas-pendentes', ['--horas' => 2])->assertSuccessful();

        $disco->assertMissing(LivroLookupService::DIRETORIO_PENDENTES.'/meio.png');
    }

    public function test_servico_de_capa_descartar_ignora_pendentes_e_caminhos_fora_de_livros_capas(): void
    {
        $disco = Storage::disk('public');
        $disco->put('outra-pasta/arquivo.png', $this->imagem());
        $disco->put(LivroLookupService::DIRETORIO_PENDENTES.'/x.png', $this->imagem());

        app(LivroCapaService::class)->descartar('outra-pasta/arquivo.png');
        app(LivroCapaService::class)->descartar(LivroLookupService::DIRETORIO_PENDENTES.'/x.png');

        $disco->assertExists('outra-pasta/arquivo.png');
        $disco->assertExists(LivroLookupService::DIRETORIO_PENDENTES.'/x.png');
    }

    // ------------------------------------------------------------------ formulário

    private function autenticarComoAdmin(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'] as $acao) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => "{$acao}:Livro", 'guard_name' => 'web']));
        }

        $user = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $user->assignRole('admin');
        $this->actingAs($user);
        session(['active_role' => 'admin']);
    }

    public function test_buscar_varias_vezes_no_formulario_e_salvar_deixa_um_pendente_e_uma_capa_definitiva(): void
    {
        $this->autenticarComoAdmin();
        $this->fakeHttp();

        $tela = Livewire::test(CreateLivro::class);

        foreach (range(1, 3) as $_) {
            $tela->mountAction('buscarIsbn')
                ->setActionData(['isbn_busca' => self::ISBN])
                ->callMountedAction()
                ->assertHasNoActionErrors();
        }

        $this->assertCount(1, $this->pendentes(), 'Três cliques, um único arquivo.');

        $tela->call('create')->assertHasNoFormErrors();

        $livro = Livro::where('isbn', self::ISBN)->firstOrFail();
        $this->assertStringStartsWith('livros/capas/'.self::ISBN.'_', $livro->capa);
        Storage::disk('public')->assertExists($livro->capa);
    }

    public function test_formulario_com_isbn_inexistente_nao_grava_nada(): void
    {
        $this->autenticarComoAdmin();
        Http::fake(['*' => Http::response('', 404)]);

        Livewire::test(CreateLivro::class)
            ->mountAction('buscarIsbn')
            ->setActionData(['isbn_busca' => '9780000000002'])
            ->callMountedAction()
            ->assertNotified('Livro não encontrado');

        $this->assertSame([], $this->pendentes());
    }
}
