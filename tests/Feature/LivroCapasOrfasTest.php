<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Livro;
use App\Services\LivroCapaService;
use App\Services\LivroLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * biblioteca:limpar-capas-orfas — remove de livros/capas os arquivos que nenhum livro usa (sobras de quando cada clique
 * em "Buscar por ISBN" gravava uma capa nova). Seguro por padrão: só lista; só apaga com --apagar.
 */
class LivroCapasOrfasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /**
     * Cria um arquivo de 2 KB no disco `public` com a idade informada.
     */
    private function arquivo(string $caminho, float $idadeDias = 30): string
    {
        $disco = Storage::disk('public');
        $disco->put($caminho, str_repeat('x', 2048));
        touch($disco->path($caminho), (int) now()->subSeconds((int) ($idadeDias * 86400))->getTimestamp());
        clearstatcache();

        return $caminho;
    }

    private function livroComCapa(?string $capa, string $isbn = '9780000000001'): Livro
    {
        return Livro::create([
            'titulo' => 'Livro '.$isbn,
            'autor' => 'Autor',
            'isbn' => $isbn,
            'quantidade_total' => 1,
            'quantidade_disponivel' => 1,
            'capa' => $capa,
        ]);
    }

    /**
     * Cenário típico: uma capa em uso, duas órfãs antigas.
     *
     * @return array{em_uso: string, orfa_a: string, orfa_b: string}
     */
    private function cenarioComOrfas(): array
    {
        $emUso = $this->arquivo('livros/capas/9788574121871_emuso.jpg');
        $this->livroComCapa($emUso);

        return [
            'em_uso' => $emUso,
            'orfa_a' => $this->arquivo('livros/capas/9788574121871_orfaA.jpg'),
            'orfa_b' => $this->arquivo('livros/capas/9788574121871_orfaB.jpg'),
        ];
    }

    // ------------------------------------------------------------------ comando: padrão seguro

    public function test_por_padrao_apenas_lista_e_nao_apaga_nada(): void
    {
        $arquivos = $this->cenarioComOrfas();

        $this->artisan('biblioteca:limpar-capas-orfas')
            ->expectsOutputToContain('livros/capas/9788574121871_orfaA.jpg')
            ->expectsOutputToContain('2 arquivo(s) (4,0 KB) órfão(s). Nada foi apagado.')
            ->assertSuccessful();

        foreach ($arquivos as $caminho) {
            Storage::disk('public')->assertExists($caminho);
        }
    }

    public function test_o_cabecalho_mostra_o_disco_e_o_banco_comparados(): void
    {
        $this->cenarioComOrfas();

        $this->artisan('biblioteca:limpar-capas-orfas')
            ->expectsOutputToContain('Disco:')
            ->expectsOutputToContain('Banco:')
            ->expectsOutputToContain('Capas em uso pelos livros: 1 (1 existem neste disco)')
            ->assertSuccessful();
    }

    public function test_com_apagar_e_confirmacao_remove_so_as_orfas(): void
    {
        $arquivos = $this->cenarioComOrfas();

        $this->artisan('biblioteca:limpar-capas-orfas', ['--apagar' => true])
            ->expectsConfirmation('Apagar 2 arquivo(s) (4,0 KB)?', 'yes')
            ->expectsOutputToContain('2 arquivo(s) apagado(s).')
            ->assertSuccessful();

        Storage::disk('public')->assertMissing($arquivos['orfa_a']);
        Storage::disk('public')->assertMissing($arquivos['orfa_b']);
        Storage::disk('public')->assertExists($arquivos['em_uso']);
    }

    public function test_recusar_a_confirmacao_nao_apaga_nada(): void
    {
        $arquivos = $this->cenarioComOrfas();

        $this->artisan('biblioteca:limpar-capas-orfas', ['--apagar' => true])
            ->expectsConfirmation('Apagar 2 arquivo(s) (4,0 KB)?', 'no')
            ->expectsOutputToContain('Cancelado. Nada foi apagado.')
            ->assertSuccessful();

        foreach ($arquivos as $caminho) {
            Storage::disk('public')->assertExists($caminho);
        }
    }

    public function test_force_dispensa_a_confirmacao(): void
    {
        $arquivos = $this->cenarioComOrfas();

        $this->artisan('biblioteca:limpar-capas-orfas', ['--apagar' => true, '--force' => true])
            ->expectsOutputToContain('2 arquivo(s) apagado(s).')
            ->assertSuccessful();

        Storage::disk('public')->assertMissing($arquivos['orfa_a']);
        Storage::disk('public')->assertExists($arquivos['em_uso']);
    }

    // ------------------------------------------------------------------ o que nunca é apagado

    public function test_arquivos_recentes_pendentes_ocultos_subpastas_e_outras_pastas_sao_preservados(): void
    {
        $this->cenarioComOrfas();
        $recente = $this->arquivo('livros/capas/recente.jpg', 2);
        $pendente = $this->arquivo(LivroLookupService::DIRETORIO_PENDENTES.'/9788574121871.jpg');
        $oculto = $this->arquivo('livros/capas/.gitignore');
        $subpasta = $this->arquivo('livros/capas/antigas/velha.jpg');
        $outraPasta = $this->arquivo('outra-pasta/arquivo.jpg');

        $this->artisan('biblioteca:limpar-capas-orfas', ['--apagar' => true, '--force' => true])->assertSuccessful();

        foreach ([$recente, $pendente, $oculto, $subpasta, $outraPasta] as $caminho) {
            Storage::disk('public')->assertExists($caminho);
        }
    }

    /**
     * @return array{tres_dias: string, meio_dia: string}
     */
    private function cenarioComArquivosRecentes(): array
    {
        $this->livroComCapa($this->arquivo('livros/capas/emuso.jpg'));

        return [
            'tres_dias' => $this->arquivo('livros/capas/tres-dias.jpg', 3),
            'meio_dia' => $this->arquivo('livros/capas/meio-dia.jpg', 0.5),
        ];
    }

    public function test_o_prazo_padrao_de_sete_dias_preserva_arquivos_recentes(): void
    {
        $arquivos = $this->cenarioComArquivosRecentes();

        $this->artisan('biblioteca:limpar-capas-orfas', ['--apagar' => true, '--force' => true])
            ->expectsOutputToContain('Nenhuma capa órfã encontrada.')
            ->assertSuccessful();

        Storage::disk('public')->assertExists($arquivos['tres_dias']);
        Storage::disk('public')->assertExists($arquivos['meio_dia']);
    }

    public function test_o_prazo_em_dias_informado_e_respeitado(): void
    {
        $arquivos = $this->cenarioComArquivosRecentes();

        $this->artisan('biblioteca:limpar-capas-orfas', ['--apagar' => true, '--force' => true, '--dias' => 2])
            ->expectsOutputToContain('1 arquivo(s) apagado(s).')
            ->assertSuccessful();

        Storage::disk('public')->assertMissing($arquivos['tres_dias']);
        Storage::disk('public')->assertExists($arquivos['meio_dia']);
    }

    public function test_prazo_zero_vale_um_dia_para_nao_apagar_o_que_acabou_de_ser_gravado(): void
    {
        $arquivos = $this->cenarioComArquivosRecentes();

        $this->artisan('biblioteca:limpar-capas-orfas', ['--apagar' => true, '--force' => true, '--dias' => 0])
            ->expectsOutputToContain('1 arquivo(s) apagado(s).')
            ->assertSuccessful();

        Storage::disk('public')->assertMissing($arquivos['tres_dias']);
        Storage::disk('public')->assertExists($arquivos['meio_dia']);
    }

    public function test_capa_com_barra_inicial_no_banco_continua_contando_como_em_uso(): void
    {
        $arquivo = $this->arquivo('livros/capas/barra.jpg');
        $this->livroComCapa('/'.$arquivo);
        $this->arquivo('livros/capas/orfa.jpg');

        $this->artisan('biblioteca:limpar-capas-orfas', ['--apagar' => true, '--force' => true])
            ->expectsOutputToContain('1 arquivo(s) apagado(s).')
            ->assertSuccessful();

        Storage::disk('public')->assertExists($arquivo);
    }

    public function test_capa_remota_nao_conta_como_referencia_local_nem_como_ausente(): void
    {
        $this->livroComCapa('https://exemplo.com/capa.jpg', '9780000000002');
        $this->livroComCapa($this->arquivo('livros/capas/local.jpg'), '9780000000003');

        $diag = app(LivroCapaService::class)->diagnosticarOrfas();

        $this->assertSame(1, $diag['referenciadas']);
        $this->assertSame(0, $diag['ausentes']);
        $this->assertSame(1, $diag['em_uso']);
    }

    // ------------------------------------------------------------------ armazenamento que não pertence ao banco

    public function test_se_nenhuma_capa_em_uso_existe_no_disco_recusa_apagar(): void
    {
        // O banco diz que há capas, mas este disco não tem nenhuma delas: é o armazenamento de outra máquina.
        $this->livroComCapa('livros/capas/so-existe-no-servidor.jpg');
        $estranho = $this->arquivo('livros/capas/qualquer.jpg');

        $this->artisan('biblioteca:limpar-capas-orfas', ['--apagar' => true, '--force' => true])
            ->expectsOutputToContain('provavelmente NÃO pertence a este banco')
            ->assertFailed();

        Storage::disk('public')->assertExists($estranho);
    }

    public function test_a_recusa_vale_tambem_sem_apagar(): void
    {
        $this->livroComCapa('livros/capas/so-existe-no-servidor.jpg');
        $this->arquivo('livros/capas/qualquer.jpg');

        $this->artisan('biblioteca:limpar-capas-orfas')->assertFailed();
    }

    public function test_capas_em_uso_parcialmente_ausentes_nao_impedem_a_limpeza(): void
    {
        $this->livroComCapa($this->arquivo('livros/capas/presente.jpg'), '9780000000004');
        $this->livroComCapa('livros/capas/perdida.jpg', '9780000000005');
        $orfa = $this->arquivo('livros/capas/orfa.jpg');

        $this->artisan('biblioteca:limpar-capas-orfas', ['--apagar' => true, '--force' => true])
            ->expectsOutputToContain('Capas em uso pelos livros: 2 (1 existem neste disco)')
            ->expectsOutputToContain('1 arquivo(s) apagado(s).')
            ->assertSuccessful();

        Storage::disk('public')->assertMissing($orfa);
    }

    public function test_sem_nenhum_livro_com_capa_local_exige_force(): void
    {
        $arquivo = $this->arquivo('livros/capas/qualquer.jpg');

        $this->artisan('biblioteca:limpar-capas-orfas', ['--apagar' => true])
            ->expectsOutputToContain('Nenhum livro usa capa local neste banco')
            ->assertFailed();

        Storage::disk('public')->assertExists($arquivo);

        $this->artisan('biblioteca:limpar-capas-orfas', ['--apagar' => true, '--force' => true])
            ->expectsOutputToContain('1 arquivo(s) apagado(s).')
            ->assertSuccessful();

        Storage::disk('public')->assertMissing($arquivo);
    }

    public function test_sem_orfas_informa_e_termina_com_sucesso(): void
    {
        $this->livroComCapa($this->arquivo('livros/capas/emuso.jpg'));

        $this->artisan('biblioteca:limpar-capas-orfas', ['--apagar' => true])
            ->expectsOutputToContain('Nenhuma capa órfã encontrada.')
            ->assertSuccessful();
    }

    // ------------------------------------------------------------------ serviço

    public function test_apagar_confere_de_novo_antes_de_remover(): void
    {
        $emUso = $this->arquivo('livros/capas/emuso.jpg');
        $this->livroComCapa($emUso);
        $pendente = $this->arquivo(LivroLookupService::DIRETORIO_PENDENTES.'/x.jpg');
        $fora = $this->arquivo('outra-pasta/y.jpg');
        $subpasta = $this->arquivo('livros/capas/sub/z.jpg');
        $orfa = $this->arquivo('livros/capas/orfa.jpg');

        // Mesmo que alguém passe caminhos indevidos, só o órfão legítimo é removido.
        $apagados = app(LivroCapaService::class)->apagarOrfas([
            $emUso, $pendente, $fora, $subpasta, 'livros/capas/../outra-pasta/y.jpg', $orfa,
        ]);

        $this->assertSame(1, $apagados);
        Storage::disk('public')->assertMissing($orfa);

        foreach ([$emUso, $pendente, $fora, $subpasta] as $caminho) {
            Storage::disk('public')->assertExists($caminho);
        }
    }

    public function test_o_comando_nao_esta_no_agendador(): void
    {
        // A limpeza de órfãs é sempre uma decisão de quem opera o servidor (lista primeiro, apaga depois).
        $comandos = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->map(fn ($evento): string => (string) $evento->command)
            ->implode(' ');

        $this->assertStringNotContainsString('limpar-capas-orfas', $comandos);
        $this->assertStringContainsString('limpar-capas-pendentes', $comandos);
    }
}
