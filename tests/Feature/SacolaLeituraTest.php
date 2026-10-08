<?php

namespace Tests\Feature;

use App\Enums\StatusSacolaLeitura;
use App\Filament\Resources\SacolasLeitura\Pages\GerenciarSacolaLeitura;
use App\Models\Livro;
use App\Models\Pessoa;
use App\Models\SacolaLeitura;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SacolaLeituraTest extends TestCase
{
    use RefreshDatabase;

    private function autenticarComoAdmin(): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'] as $acao) {
            foreach (['Livro', 'SacolaLeitura', 'Turma', 'Pessoa'] as $model) {
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

    public function test_sacola_leitura_gera_codigo_automatico_ao_criar(): void
    {
        $turma = Turma::factory()->create(['nome' => '1º Ano Alpha']);
        $professor = Pessoa::factory()->create(['nome' => 'Professora Fernanda']);

        $sacola = SacolaLeitura::create([
            'titulo' => 'Sacola Literária - 1º Ano Alpha',
            'turma_id' => $turma->id,
            'responsavel_id' => $professor->id,
            'data_retirada' => now()->toDateString(),
            'data_prevista_devolucao' => now()->addDays(30)->toDateString(),
            'status' => StatusSacolaLeitura::EmCirculacao,
        ]);

        $this->assertNotNull($sacola->codigo);
        $this->assertStringStartsWith('SAC-', $sacola->codigo);
    }

    public function test_adicionar_livro_a_sacola_decrementa_estoque_disponivel(): void
    {
        $livro = Livro::create([
            'titulo' => 'O Pequeno Príncipe',
            'autor' => 'Antoine de Saint-Exupéry',
            'quantidade_total' => 2,
            'quantidade_disponivel' => 2,
        ]);

        $sacola = SacolaLeitura::create([
            'titulo' => 'Sacola de Leitura Teste',
            'data_retirada' => now()->toDateString(),
            'data_prevista_devolucao' => now()->addDays(15)->toDateString(),
        ]);

        $item = $sacola->adicionarLivro($livro);

        $this->assertDatabaseHas('sacola_leitura_itens', [
            'id' => $item->id,
            'sacola_id' => $sacola->id,
            'livro_id' => $livro->id,
            'devolvido' => false,
        ]);

        $livro->refresh();
        $this->assertEquals(1, $livro->quantidade_disponivel);
        $this->assertEquals(1, $sacola->totalLivros());
        $this->assertEquals(1, $sacola->totalPendentes());
    }

    public function test_livro_sem_estoque_nao_pode_ser_adicionado_a_sacola(): void
    {
        $this->expectException(\DomainException::class);

        $livro = Livro::create([
            'titulo' => 'Livro Esgotado',
            'autor' => 'Autor Desconhecido',
            'quantidade_total' => 1,
            'quantidade_disponivel' => 0,
        ]);

        $sacola = SacolaLeitura::create([
            'titulo' => 'Sacola Sem Estoque',
            'data_retirada' => now()->toDateString(),
            'data_prevista_devolucao' => now()->addDays(15)->toDateString(),
        ]);

        $sacola->adicionarLivro($livro);
    }

    public function test_devolver_item_individual_da_sacola_recoloca_exemplar_no_acervo(): void
    {
        $livro = Livro::create([
            'titulo' => 'Histórias da Menina',
            'autor' => 'Autora Fictícia',
            'quantidade_total' => 1,
            'quantidade_disponivel' => 1,
        ]);

        $sacola = SacolaLeitura::create([
            'titulo' => 'Sacola Devolução Individual',
            'data_retirada' => now()->toDateString(),
            'data_prevista_devolucao' => now()->addDays(20)->toDateString(),
        ]);

        $item = $sacola->adicionarLivro($livro);
        $livro->refresh();
        $this->assertEquals(0, $livro->quantidade_disponivel);

        $sacola->devolverItem($item);

        $item->refresh();
        $livro->refresh();
        $sacola->refresh();

        $this->assertTrue($item->devolvido);
        $this->assertEquals(1, $livro->quantidade_disponivel);
        $this->assertEquals(StatusSacolaLeitura::Devolvida, $sacola->status);
        $this->assertNotNull($sacola->data_devolucao);
    }

    public function test_devolver_todos_itens_fecha_sacola_e_recoloca_todos_exemplares_no_acervo(): void
    {
        $livro1 = Livro::create(['titulo' => 'Livro 1', 'autor' => 'Autor 1', 'quantidade_total' => 2, 'quantidade_disponivel' => 2]);
        $livro2 = Livro::create(['titulo' => 'Livro 2', 'autor' => 'Autor 2', 'quantidade_total' => 2, 'quantidade_disponivel' => 2]);

        $sacola = SacolaLeitura::create([
            'titulo' => 'Sacola Devolução Completa',
            'data_retirada' => now()->toDateString(),
            'data_prevista_devolucao' => now()->addDays(20)->toDateString(),
        ]);

        $sacola->adicionarLivro($livro1);
        $sacola->adicionarLivro($livro2);

        $this->assertEquals(1, $livro1->fresh()->quantidade_disponivel);
        $this->assertEquals(1, $livro2->fresh()->quantidade_disponivel);

        $sacola->devolverTodosItens();

        $this->assertEquals(2, $livro1->fresh()->quantidade_disponivel);
        $this->assertEquals(2, $livro2->fresh()->quantidade_disponivel);
        $this->assertEquals(StatusSacolaLeitura::Devolvida, $sacola->fresh()->status);
        $this->assertEquals(0, $sacola->totalPendentes());
        $this->assertEquals(2, $sacola->totalDevolvidos());
    }

    public function test_excluir_sacola_com_itens_pendentes_recoloca_exemplares_no_acervo(): void
    {
        $livro = Livro::create(['titulo' => 'Livro Protegido', 'autor' => 'Autor Seguro', 'quantidade_total' => 1, 'quantidade_disponivel' => 1]);

        $sacola = SacolaLeitura::create([
            'titulo' => 'Sacola para Excluir',
            'data_retirada' => now()->toDateString(),
            'data_prevista_devolucao' => now()->addDays(10)->toDateString(),
        ]);

        $sacola->adicionarLivro($livro);
        $this->assertEquals(0, $livro->fresh()->quantidade_disponivel);

        $sacola->delete();

        $this->assertEquals(1, $livro->fresh()->quantidade_disponivel);
    }

    public function test_rota_impressao_ficha_sacola_renderiza_com_sucesso(): void
    {
        $this->autenticarComoAdmin();

        $turma = Turma::factory()->create(['nome' => 'Infantil 5 B']);
        $professor = Pessoa::factory()->create(['nome' => 'Prof. Cláudia']);

        $sacola = SacolaLeitura::create([
            'titulo' => 'Sacola Literária Infantil',
            'turma_id' => $turma->id,
            'responsavel_id' => $professor->id,
            'data_retirada' => now()->toDateString(),
            'data_prevista_devolucao' => now()->addDays(15)->toDateString(),
        ]);

        $livro = Livro::create(['titulo' => 'O Gato Xadrez', 'autor' => 'Isa Mara', 'quantidade_total' => 1, 'quantidade_disponivel' => 1]);
        $sacola->adicionarLivro($livro);

        $response = $this->get(route('biblioteca.sacolas.ficha', $sacola));

        $response->assertOk();
        $response->assertSee('Infantil 5 B');
        $response->assertSee('Prof. Cláudia');
        $response->assertSee('O Gato Xadrez');
        $response->assertSee($sacola->codigo);
    }

    public function test_livewire_gerenciar_sacola_adiciona_e_devolve_por_codigo(): void
    {
        $this->autenticarComoAdmin();

        $livro = Livro::create([
            'titulo' => 'Reinações de Narizinho',
            'autor' => 'Monteiro Lobato',
            'quantidade_total' => 1,
            'quantidade_disponivel' => 1,
        ]);

        $sacola = SacolaLeitura::create([
            'titulo' => 'Sacola Livewire',
            'data_retirada' => now()->toDateString(),
            'data_prevista_devolucao' => now()->addDays(20)->toDateString(),
        ]);

        // Inclusão na sacola via Livewire
        Livewire::test(GerenciarSacolaLeitura::class, ['record' => $sacola])
            ->set('codigo_livro_adicionar', $livro->codigo)
            ->call('adicionarLivroPorCodigo')
            ->assertHasNoErrors()
            ->assertNotified();

        $this->assertEquals(0, $livro->fresh()->quantidade_disponivel);
        $this->assertEquals(1, $sacola->fresh()->totalPendentes());

        // Devolução da sacola via Livewire
        Livewire::test(GerenciarSacolaLeitura::class, ['record' => $sacola])
            ->set('codigo_livro_devolver', $livro->codigo)
            ->call('devolverLivroPorCodigo')
            ->assertHasNoErrors()
            ->assertNotified();

        $this->assertEquals(1, $livro->fresh()->quantidade_disponivel);
        $this->assertEquals(StatusSacolaLeitura::Devolvida, $sacola->fresh()->status);
    }

    public function test_livro_com_sacola_em_aberto_nao_pode_ser_excluido(): void
    {
        $livro = Livro::create(['titulo' => 'Livro na Sacola Ativa', 'autor' => 'Autor Teste', 'quantidade_total' => 1, 'quantidade_disponivel' => 1]);

        $sacola = SacolaLeitura::create([
            'titulo' => 'Sacola Bloqueio',
            'data_retirada' => now()->toDateString(),
            'data_prevista_devolucao' => now()->addDays(10)->toDateString(),
        ]);

        $sacola->adicionarLivro($livro);

        $resultado = $livro->delete();

        $this->assertFalse($resultado);
        $this->assertDatabaseHas('livros', ['id' => $livro->id]);
    }
}
