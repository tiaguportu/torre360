<?php

namespace Tests\Feature;

use App\Enums\SituacaoMatricula;
use App\Enums\StatusEmprestimo;
use App\Filament\Pages\CirculacaoBiblioteca;
use App\Filament\Resources\Inventarios\Pages\ConferenciaInventario;
use App\Models\Emprestimo;
use App\Models\InventarioAcervo;
use App\Models\Livro;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Preceptoria;
use App\Models\User;
use App\Services\BarcodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BibliotecaAvancadaTest extends TestCase
{
    use RefreshDatabase;

    private function autenticarComoAdmin(): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'] as $acao) {
            foreach (['Livro', 'Emprestimo', 'InventarioAcervo', 'Preceptoria', 'Matricula'] as $model) {
                $permissao = Permission::firstOrCreate(['name' => "{$acao}:{$model}", 'guard_name' => 'web']);
                $role->givePermissionTo($permissao);
            }
        }

        $user = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $user->assignRole('admin');
        $this->actingAs($user);
        session(['active_role' => 'admin']);

        return $user;
    }

    public function test_livro_gera_codigo_automatico_ao_criar(): void
    {
        $livro = Livro::create([
            'titulo' => 'Dom Casmurro',
            'autor' => 'Machado de Assis',
            'quantidade_total' => 2,
            'quantidade_disponivel' => 2,
        ]);

        $this->assertNotNull($livro->codigo);
        $this->assertStringStartsWith('LIV-', $livro->codigo);
        $this->assertEquals($livro->codigo, $livro->identificador_leitor);
    }

    public function test_livro_com_faixa_etaria_e_segmentos_pedagogicos(): void
    {
        $livro = Livro::create([
            'titulo' => 'Chapeuzinho Vermelho',
            'autor' => 'Irmãos Grimm',
            'faixa_etaria' => '4 a 6 anos',
            'segmentos' => ['educacao_infantil', 'fundamental_1'],
            'quantidade_total' => 3,
            'quantidade_disponivel' => 3,
        ]);

        $this->assertEquals('4 a 6 anos', $livro->faixa_etaria);
        $this->assertIsArray($livro->segmentos);
        $this->assertContains('educacao_infantil', $livro->segmentos);
    }

    public function test_barcode_service_gera_svg_valido(): void
    {
        $service = app(BarcodeService::class);
        $svg = $service->gerarSvg('LIV-00042', 45, 1.8);

        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringContainsString('viewBox="0 0', $svg);
        $this->assertStringContainsString('<rect', $svg);
    }

    public function test_rota_impressao_etiquetas_renderiza_com_sucesso(): void
    {
        $this->autenticarComoAdmin();

        $livro = Livro::create([
            'titulo' => 'O Menino Maluquinho',
            'autor' => 'Ziraldo',
            'quantidade_total' => 1,
            'quantidade_disponivel' => 1,
        ]);

        $response = $this->get(route('biblioteca.etiquetas.imprimir', ['livros' => $livro->id]));

        $response->assertOk();
        $response->assertSee('O Menino Maluquinho');
        $response->assertSee('Ziraldo');
        $response->assertSee($livro->codigo);
    }

    public function test_balcao_circulacao_realiza_emprestimo_rapido(): void
    {
        $this->autenticarComoAdmin();

        $pessoa = Pessoa::factory()->create(['nome' => 'Lucas Silva']);
        $matricula = Matricula::factory()->create([
            'pessoa_id' => $pessoa->id,
            'situacao' => SituacaoMatricula::ATIVA,
        ]);

        $livro = Livro::create([
            'titulo' => 'Vidas Secas',
            'autor' => 'Graciliano Ramos',
            'quantidade_total' => 2,
            'quantidade_disponivel' => 2,
        ]);

        Livewire::test(CirculacaoBiblioteca::class)
            ->set('matricula_id', $matricula->id)
            ->set('codigo_livro_emprestimo', $livro->codigo)
            ->call('realizarEmprestimo')
            ->assertHasNoErrors()
            ->assertNotified();

        $this->assertDatabaseHas('emprestimos', [
            'livro_id' => $livro->id,
            'matricula_id' => $matricula->id,
            'status' => StatusEmprestimo::Emprestado->value,
        ]);

        $livro->refresh();
        $this->assertEquals(1, $livro->quantidade_disponivel);
    }

    public function test_balcao_circulacao_realiza_devolucao_em_um_bip(): void
    {
        $this->autenticarComoAdmin();

        $pessoa = Pessoa::factory()->create(['nome' => 'Ana Clara']);
        $matricula = Matricula::factory()->create([
            'pessoa_id' => $pessoa->id,
            'situacao' => SituacaoMatricula::ATIVA,
        ]);

        $livro = Livro::create([
            'titulo' => 'A Moreninha',
            'autor' => 'Joaquim Manuel de Macedo',
            'quantidade_total' => 1,
            'quantidade_disponivel' => 0,
        ]);

        $emprestimo = Emprestimo::create([
            'livro_id' => $livro->id,
            'matricula_id' => $matricula->id,
            'data_emprestimo' => now()->subDays(5),
            'data_prevista_devolucao' => now()->addDays(9),
            'status' => StatusEmprestimo::Emprestado,
        ]);

        Livewire::test(CirculacaoBiblioteca::class)
            ->set('codigo_livro_devolucao', $livro->codigo)
            ->call('realizarDevolucao')
            ->assertHasNoErrors()
            ->assertNotified();

        $emprestimo->refresh();
        $livro->refresh();

        $this->assertEquals(StatusEmprestimo::Devolvido, $emprestimo->status);
        $this->assertNotNull($emprestimo->data_devolucao);
        $this->assertEquals(1, $livro->quantidade_disponivel);
    }

    public function test_inventario_acervo_fluxo_conferencia_e_faltantes(): void
    {
        $user = $this->autenticarComoAdmin();

        $livroPresente = Livro::create([
            'titulo' => 'Livro Na Estante',
            'autor' => 'Autor 1',
            'quantidade_total' => 1,
            'quantidade_disponivel' => 1,
        ]);

        $livroFaltante = Livro::create([
            'titulo' => 'Livro Extraviado',
            'autor' => 'Autor 2',
            'quantidade_total' => 1,
            'quantidade_disponivel' => 1,
        ]);

        $inventario = InventarioAcervo::create([
            'titulo' => 'Inventário Semestral 2026',
            'data_inicio' => now()->toDateString(),
            'status' => 'em_andamento',
            'user_id' => $user->id,
        ]);

        Livewire::test(ConferenciaInventario::class, ['record' => $inventario])
            ->set('codigo_bipado', $livroPresente->codigo)
            ->call('biparLivro')
            ->assertHasNoErrors()
            ->assertNotified();

        $this->assertDatabaseHas('inventario_itens', [
            'inventario_id' => $inventario->id,
            'livro_id' => $livroPresente->id,
        ]);

        $component = Livewire::test(ConferenciaInventario::class, ['record' => $inventario]);
        $this->assertEquals(1, $component->get('totalConferidos'));
        $this->assertCount(1, $component->get('livrosFaltantes'));
        $this->assertEquals($livroFaltante->id, $component->get('livrosFaltantes')->first()->id);
    }

    public function test_historico_leitura_componente_preceptoria(): void
    {
        $this->autenticarComoAdmin();

        $pessoa = Pessoa::factory()->create(['nome' => 'Mateus']);
        $matricula = Matricula::factory()->create([
            'pessoa_id' => $pessoa->id,
            'situacao' => SituacaoMatricula::ATIVA,
        ]);

        $livro = Livro::create([
            'titulo' => 'Memórias Póstumas de Brás Cubas',
            'autor' => 'Machado de Assis',
            'quantidade_total' => 1,
            'quantidade_disponivel' => 0,
        ]);

        Emprestimo::create([
            'livro_id' => $livro->id,
            'matricula_id' => $matricula->id,
            'data_emprestimo' => now()->subDays(2),
            'data_prevista_devolucao' => now()->addDays(12),
            'status' => StatusEmprestimo::Emprestado,
        ]);

        $preceptoria = Preceptoria::factory()->create([
            'matricula_id' => $matricula->id,
        ]);

        $view = $this->view('filament.components.preceptoria-historico-leitura', [
            'preceptoria' => $preceptoria,
        ]);

        $view->assertSee('Memórias Póstumas de Brás Cubas');
        $view->assertSee('Machado de Assis');
        $view->assertSee('Mateus');
    }

    public function test_balcao_circulacao_processa_leitura_camera_emprestimo(): void
    {
        $this->autenticarComoAdmin();

        $pessoa = Pessoa::factory()->create(['nome' => 'Beatriz Santos']);
        $matricula = Matricula::factory()->create([
            'pessoa_id' => $pessoa->id,
            'situacao' => SituacaoMatricula::ATIVA,
        ]);

        $livro = Livro::create([
            'titulo' => 'Capitães da Areia',
            'autor' => 'Jorge Amado',
            'quantidade_total' => 2,
            'quantidade_disponivel' => 2,
        ]);

        Livewire::test(CirculacaoBiblioteca::class)
            ->set('matricula_id', $matricula->id)
            ->call('processarLeituraEmprestimo', $livro->codigo)
            ->assertHasNoErrors()
            ->assertNotified();

        $this->assertDatabaseHas('emprestimos', [
            'livro_id' => $livro->id,
            'matricula_id' => $matricula->id,
            'status' => StatusEmprestimo::Emprestado->value,
        ]);

        $livro->refresh();
        $this->assertEquals(1, $livro->quantidade_disponivel);
    }

    public function test_balcao_circulacao_processa_leitura_camera_devolucao(): void
    {
        $this->autenticarComoAdmin();

        $pessoa = Pessoa::factory()->create(['nome' => 'Gabriel Ramos']);
        $matricula = Matricula::factory()->create([
            'pessoa_id' => $pessoa->id,
            'situacao' => SituacaoMatricula::ATIVA,
        ]);

        $livro = Livro::create([
            'titulo' => 'O Cortiço',
            'autor' => 'Aluísio Azevedo',
            'quantidade_total' => 1,
            'quantidade_disponivel' => 0,
        ]);

        $emprestimo = Emprestimo::create([
            'livro_id' => $livro->id,
            'matricula_id' => $matricula->id,
            'data_emprestimo' => now()->subDays(3),
            'data_prevista_devolucao' => now()->addDays(11),
            'status' => StatusEmprestimo::Emprestado,
        ]);

        Livewire::test(CirculacaoBiblioteca::class)
            ->call('processarLeituraDevolucao', $livro->codigo)
            ->assertHasNoErrors()
            ->assertNotified();

        $emprestimo->refresh();
        $livro->refresh();

        $this->assertEquals(StatusEmprestimo::Devolvido, $emprestimo->status);
        $this->assertEquals(1, $livro->quantidade_disponivel);
    }

    public function test_conferencia_inventario_processa_leitura_camera(): void
    {
        $user = $this->autenticarComoAdmin();

        $livro = Livro::create([
            'titulo' => 'Iracema',
            'autor' => 'José de Alencar',
            'quantidade_total' => 1,
            'quantidade_disponivel' => 1,
        ]);

        $inventario = InventarioAcervo::create([
            'titulo' => 'Inventário Rápido com Celular',
            'data_inicio' => now()->toDateString(),
            'status' => 'em_andamento',
            'user_id' => $user->id,
        ]);

        Livewire::test(ConferenciaInventario::class, ['record' => $inventario])
            ->call('processarLeituraInventario', $livro->codigo)
            ->assertHasNoErrors()
            ->assertNotified();

        $this->assertDatabaseHas('inventario_itens', [
            'inventario_id' => $inventario->id,
            'livro_id' => $livro->id,
        ]);
    }
}
