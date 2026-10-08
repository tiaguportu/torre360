<?php

namespace Tests\Feature;

use App\Filament\Resources\Livros\Pages\CreateLivro;
use App\Filament\Resources\Livros\Pages\EditLivro;
use App\Filament\Resources\Livros\Pages\ListLivros;
use App\Models\Livro;
use App\Models\User;
use App\Services\LivroLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LivroIsbnLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Sem rede nos testes: nada fora do que foi declarado em fakeHttp() pode sair para a internet.
        Http::preventStrayRequests();
        // O DNS real não é consultado (os hosts dos testes são fictícios); a proteção contra SSRF é testada em LivroLookupCapaTest.
        config(['services.livros.validar_dns' => false]);
    }

    /**
     * Imagem real (a busca recusa arquivos que não sejam imagens de verdade).
     */
    private function imagemBinaria(int $largura = 120, int $altura = 180, string $formato = 'png'): string
    {
        $imagem = imagecreatetruecolor($largura, $altura);
        imagefill($imagem, 0, 0, imagecolorallocate($imagem, 30, 90, 160));

        ob_start();
        $formato === 'jpeg' ? imagejpeg($imagem) : imagepng($imagem);

        return (string) ob_get_clean();
    }

    /**
     * Declara as respostas das fontes; tudo que não foi declarado responde 404 (a busca consulta as três fontes em paralelo).
     *
     * @param  array<string, mixed>  $respostas
     */
    private function fakeHttp(array $respostas): void
    {
        Http::fake($respostas + ['*' => Http::response('', 404)]);
    }

    private function autenticarComoAdmin(): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'] as $acao) {
            $permissao = Permission::firstOrCreate(['name' => "{$acao}:Livro", 'guard_name' => 'web']);
            $role->givePermissionTo($permissao);
        }

        $user = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $user->assignRole('admin');
        $this->actingAs($user);
        session(['active_role' => 'admin']);

        return $user;
    }

    public function test_isbn_invalido_retorna_erro_amigavel(): void
    {
        $service = app(LivroLookupService::class);

        $resultado = $service->buscarPorIsbn('123');

        $this->assertFalse($resultado['sucesso']);
        $this->assertStringContainsString('10 ou 13 dígitos', $resultado['mensagem']);
    }

    public function test_isbn_busca_open_library_com_sucesso_e_baixa_capa(): void
    {
        Storage::fake('public');

        $fakeIsbn = '9788576082675';
        $fakeImgUrl = 'https://covers.openlibrary.org/b/id/12345-L.jpg';
        $fakeImgBinary = $this->imagemBinaria();

        $this->fakeHttp([
            'https://openlibrary.org/api/books*' => Http::response([
                "ISBN:{$fakeIsbn}" => [
                    'title' => 'Código Limpo',
                    'authors' => [
                        ['name' => 'Robert C. Martin'],
                    ],
                    'publishers' => [
                        ['name' => 'Alta Books'],
                    ],
                    'subjects' => [
                        ['name' => 'Engenharia de Software'],
                    ],
                    'cover' => [
                        'large' => $fakeImgUrl,
                    ],
                ],
            ], 200),
            $fakeImgUrl => Http::response($fakeImgBinary, 200, ['Content-Type' => 'image/png']),
        ]);

        $service = app(LivroLookupService::class);
        $resultado = $service->buscarPorIsbn($fakeIsbn);

        $this->assertTrue($resultado['sucesso']);
        $this->assertEquals('Código Limpo', $resultado['dados']['titulo']);
        $this->assertEquals('Robert C. Martin', $resultado['dados']['autor']);
        $this->assertEquals('Alta Books', $resultado['dados']['editora']);
        $this->assertEquals('Engenharia de Software', $resultado['dados']['categoria']);
        $this->assertNotNull($resultado['dados']['capa']);

        Storage::disk('public')->assertExists($resultado['dados']['capa']);
    }

    public function test_isbn_busca_brasil_api_como_fallback(): void
    {
        Storage::fake('public');

        $fakeIsbn = '9788535902778';
        $fakeImgUrl = 'https://brasilapi.com.br/capa.jpg';
        $fakeImgBinary = $this->imagemBinaria();

        $this->fakeHttp([
            'https://openlibrary.org/api/books*' => Http::response([], 200),
            'https://brasilapi.com.br/api/isbn/v1/*' => Http::response([
                'isbn' => $fakeIsbn,
                'title' => 'Dom Casmurro',
                'authors' => ['Machado de Assis'],
                'publisher' => 'Editora Garnier',
                'subjects' => ['Literatura Brasileira'],
                'cover_url' => $fakeImgUrl,
            ], 200),
            $fakeImgUrl => Http::response($fakeImgBinary, 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $service = app(LivroLookupService::class);
        $resultado = $service->buscarPorIsbn($fakeIsbn);

        $this->assertTrue($resultado['sucesso']);
        $this->assertEquals('Dom Casmurro', $resultado['dados']['titulo']);
        $this->assertEquals('Machado de Assis', $resultado['dados']['autor']);
        $this->assertEquals('Editora Garnier', $resultado['dados']['editora']);
        $this->assertNotNull($resultado['dados']['capa']);

        Storage::disk('public')->assertExists($resultado['dados']['capa']);
    }

    public function test_isbn_quando_nao_encontrado(): void
    {
        $this->fakeHttp([
            '*' => Http::response([], 404),
        ]);

        $service = app(LivroLookupService::class);
        $resultado = $service->buscarPorIsbn('9780000000000');

        $this->assertFalse($resultado['sucesso']);
        $this->assertStringContainsString('Nenhum dado encontrado', $resultado['mensagem']);
    }

    public function test_pagina_create_livro_renderiza_com_sucesso_e_contem_acoes(): void
    {
        $this->autenticarComoAdmin();

        Livewire::test(CreateLivro::class)
            ->assertSuccessful()
            ->assertActionExists('ajuda')
            ->assertActionExists('buscarIsbn');
    }

    public function test_cadastro_manual_de_livro_com_foto_da_capa(): void
    {
        $this->autenticarComoAdmin();
        Storage::fake('public');

        $file = UploadedFile::fake()->image('minha_capa.jpg');

        Livewire::test(CreateLivro::class)
            ->fillForm([
                'titulo' => 'O Pequeno Príncipe',
                'autor' => 'Antoine de Saint-Exupéry',
                'editora' => 'Agir',
                'isbn' => '9788522005239',
                'categoria' => 'Infantojuvenil',
                'quantidade_total' => 5,
                'quantidade_disponivel' => 5,
                'capa' => $file,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('livros', [
            'titulo' => 'O Pequeno Príncipe',
            'autor' => 'Antoine de Saint-Exupéry',
            'isbn' => '9788522005239',
        ]);

        $livro = Livro::where('isbn', '9788522005239')->first();
        $this->assertNotNull($livro->capa);
        Storage::disk('public')->assertExists($livro->capa);
    }

    public function test_header_action_buscar_isbn_preenche_formulario(): void
    {
        $this->autenticarComoAdmin();
        Storage::fake('public');

        $fakeIsbn = '9788576082675';
        $fakeImgUrl = 'https://covers.openlibrary.org/b/id/99999-L.jpg';
        $fakeImgBinary = $this->imagemBinaria();

        $this->fakeHttp([
            'https://openlibrary.org/api/books*' => Http::response([
                "ISBN:{$fakeIsbn}" => [
                    'title' => 'Código Limpo',
                    'authors' => [['name' => 'Robert C. Martin']],
                    'publishers' => [['name' => 'Alta Books']],
                    'subjects' => [['name' => 'Engenharia de Software']],
                    'cover' => ['large' => $fakeImgUrl],
                ],
            ], 200),
            $fakeImgUrl => Http::response($fakeImgBinary, 200, ['Content-Type' => 'image/jpeg']),
        ]);

        Livewire::test(CreateLivro::class)
            ->mountAction('buscarIsbn')
            ->setActionData([
                'isbn_busca' => $fakeIsbn,
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertFormSet([
                'titulo' => 'Código Limpo',
                'autor' => 'Robert C. Martin',
                'editora' => 'Alta Books',
                'categoria' => 'Engenharia de Software',
            ]);
    }

    public function test_pagina_list_livros_renderiza_com_sucesso(): void
    {
        $this->autenticarComoAdmin();
        Livro::create([
            'titulo' => 'Livro de Teste',
            'autor' => 'Autor Teste',
            'isbn' => '1234567890',
            'quantidade_total' => 2,
            'quantidade_disponivel' => 2,
        ]);

        Livewire::test(ListLivros::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords(Livro::all());
    }

    public function test_action_ajuda_em_create_e_edit_livro(): void
    {
        $this->autenticarComoAdmin();
        $livro = Livro::create([
            'titulo' => 'Livro Teste',
            'autor' => 'Autor Teste',
            'quantidade_total' => 1,
            'quantidade_disponivel' => 1,
        ]);

        Livewire::test(CreateLivro::class)
            ->assertActionExists('ajuda');

        Livewire::test(EditLivro::class, ['record' => $livro->id])
            ->assertActionExists('ajuda');
    }

    public function test_conversao_isbn13_para_isbn10(): void
    {
        $service = app(LivroLookupService::class);

        // 978-8574121871 -> 8574121878
        $this->assertEquals('8574121878', $service->converterIsbn13ParaIsbn10('9788574121871'));

        // 978-8576082675 -> 8576082675
        $this->assertEquals('8576082675', $service->converterIsbn13ParaIsbn10('9788576082675'));
    }

    public function test_isbn_busca_capa_amazon_como_fallback(): void
    {
        Storage::fake('public');
        config(['services.livros.amazon_habilitado' => true]);

        $fakeIsbn13 = '9788574121871';
        $fakeIsbn10 = '8574121878';
        $fakeImgBinary = $this->imagemBinaria(200, 300, 'jpeg');

        $this->fakeHttp([
            'https://openlibrary.org/api/books*' => Http::response([], 200),
            'https://brasilapi.com.br/api/isbn/v1/*' => Http::response([
                'isbn' => $fakeIsbn13,
                'title' => 'O Ratinho, o Morango Vermelho Maduro e o Grande Urso Esfomeado',
                'authors' => [],
                'publisher' => 'Brinque-Book',
                'subjects' => [],
                'cover_url' => null,
            ], 200),
            "https://covers.openlibrary.org/b/isbn/{$fakeIsbn13}-L.jpg*" => Http::response('', 404),
            "https://images-na.ssl-images-amazon.com/images/P/{$fakeIsbn10}.01.L.jpg" => Http::response($fakeImgBinary, 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $service = app(LivroLookupService::class);
        $resultado = $service->buscarPorIsbn($fakeIsbn13);

        $this->assertTrue($resultado['sucesso']);
        $this->assertEquals('O Ratinho, o Morango Vermelho Maduro e o Grande Urso Esfomeado', $resultado['dados']['titulo']);
        $this->assertEquals('Brinque-Book', $resultado['dados']['editora']);
        $this->assertNotNull($resultado['dados']['capa']);

        Storage::disk('public')->assertExists($resultado['dados']['capa']);
    }

    public function test_isbn_busca_amazon_recupera_autores_quando_omitidos_por_outras_apis(): void
    {
        Storage::fake('public');
        config(['services.livros.amazon_habilitado' => true]);

        $fakeIsbn13 = '9788574121871';
        $fakeIsbn10 = '8574121878';
        $fakeHtmlAmazon = <<<'HTML'
        <html>
            <head><title>O ratinho, o morango vermelho maduro : Wood, Audrey, Wood, Don: Amazon.com.br</title></head>
            <body>
                <span id="productTitle">O Ratinho, o Morango Vermelho Maduro e o Grande Urso Esfomeado</span>
                <div id="bylineInfo">
                    <span class="author">
                        <a href="#">Audrey Wood</a>
                        <span class="contribution">(Autor)</span>
                    </span>
                    <span class="author">
                        <a href="#">Gilda de Aquino</a>
                        <span class="contribution">(Tradutor)</span>
                    </span>
                    <span class="author">
                        <a href="#">Don Wood</a>
                        <span class="contribution">(Ilustrador)</span>
                    </span>
                </div>
            </body>
        </html>
        HTML;

        $this->fakeHttp([
            'https://openlibrary.org/api/books*' => Http::response([], 200),
            'https://brasilapi.com.br/api/isbn/v1/*' => Http::response([
                'isbn' => $fakeIsbn13,
                'title' => 'O RATINHO, O MORANGO VERMELHO MADURO E O GRANDE URSO ESFOMEADO',
                'authors' => [],
                'publisher' => 'Brinque-Book',
                'cover_url' => null,
            ], 200),
            'https://www.googleapis.com/books/v1/volumes*' => Http::response([], 200),
            "https://www.amazon.com.br/dp/{$fakeIsbn10}" => Http::response($fakeHtmlAmazon, 200),
            "https://covers.openlibrary.org/b/isbn/{$fakeIsbn13}-L.jpg*" => Http::response('', 404),
            "https://images-na.ssl-images-amazon.com/images/P/{$fakeIsbn10}.01.L.jpg" => Http::response($this->imagemBinaria(200, 300, 'jpeg'), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $service = app(LivroLookupService::class);
        $resultado = $service->buscarPorIsbn($fakeIsbn13);

        $this->assertTrue($resultado['sucesso']);
        $this->assertEquals('Audrey Wood, Don Wood', $resultado['dados']['autor']);
        $this->assertEquals('Brinque-Book', $resultado['dados']['editora']);
    }
}
