<?php

namespace Tests\Feature;

use App\Enums\StatusEmprestimo;
use App\Filament\Resources\Inventarios\Pages\ConferenciaInventario;
use App\Filament\Resources\Inventarios\Pages\CreateInventario;
use App\Models\Emprestimo;
use App\Models\InventarioAcervo;
use App\Models\InventarioItem;
use App\Models\Livro;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class InventarioAcervoTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->superAdmin = User::factory()->create([
            'activated_at' => now(),
            'email_verified_at' => now(),
        ]);
        $this->superAdmin->assignRole('super_admin');
    }

    public function test_usuario_autorizado_acessa_e_cria_inventario_de_acervo(): void
    {
        $this->actingAs($this->superAdmin);
        session(['active_role' => 'super_admin']);

        $this->get('/admin/inventarios')->assertSuccessful();

        Livewire::test(CreateInventario::class)
            ->fillForm([
                'titulo' => 'Auditoria Geral 1o Semestre',
                'data_inicio' => now()->toDateString(),
                'status' => 'em_andamento',
                'observacoes' => 'Inventário anual da biblioteca principal',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('inventarios_acervo', [
            'titulo' => 'Auditoria Geral 1o Semestre',
            'status' => 'em_andamento',
        ]);
    }

    public function test_bipagem_de_livro_registra_conferencia_e_exemplares_adicionais(): void
    {
        $this->actingAs($this->superAdmin);
        session(['active_role' => 'super_admin']);

        $inventario = InventarioAcervo::create([
            'titulo' => 'Inventario Teste',
            'data_inicio' => now()->toDateString(),
            'status' => 'em_andamento',
            'user_id' => $this->superAdmin->id,
        ]);

        $livro = Livro::factory()->create([
            'titulo' => 'O Pequeno Principe',
            'codigo' => 'BARCODE123',
            'isbn' => '9788522005239',
        ]);

        $component = Livewire::test(ConferenciaInventario::class, ['record' => $inventario])
            ->assertSuccessful();

        // 1. Primeira leitura: cria o item com quantidade 1
        $component->set('codigo_bipado', 'BARCODE123')
            ->call('biparLivro')
            ->assertNotified();

        $item = InventarioItem::where('inventario_id', $inventario->id)
            ->where('livro_id', $livro->id)
            ->first();

        $this->assertNotNull($item);
        $this->assertEquals(1, $item->quantidade_conferida);

        // 2. Segunda leitura do mesmo livro: incrementa quantidade para 2
        $component->set('codigo_bipado', 'BARCODE123')
            ->call('biparLivro')
            ->assertNotified();

        $this->assertEquals(2, $item->fresh()->quantidade_conferida);
    }

    public function test_bipagem_de_codigo_inexistente_nao_cria_item_de_inventario(): void
    {
        $this->actingAs($this->superAdmin);
        session(['active_role' => 'super_admin']);

        $inventario = InventarioAcervo::create([
            'titulo' => 'Inventario Teste',
            'data_inicio' => now()->toDateString(),
            'status' => 'em_andamento',
            'user_id' => $this->superAdmin->id,
        ]);

        Livewire::test(ConferenciaInventario::class, ['record' => $inventario])
            ->set('codigo_bipado', 'CODIGO_INEXISTENTE')
            ->call('biparLivro')
            ->assertNotified();

        $this->assertEquals(0, InventarioItem::where('inventario_id', $inventario->id)->count());
    }

    public function test_concluir_e_reabrir_auditoria_de_inventario(): void
    {
        $this->actingAs($this->superAdmin);
        session(['active_role' => 'super_admin']);

        $inventario = InventarioAcervo::create([
            'titulo' => 'Inventario Conclusao',
            'data_inicio' => now()->toDateString(),
            'status' => 'em_andamento',
            'user_id' => $this->superAdmin->id,
        ]);

        $livro = Livro::factory()->create(['codigo' => 'LIV999']);

        $component = Livewire::test(ConferenciaInventario::class, ['record' => $inventario]);

        // 1. Conclui auditoria
        $component->callAction('concluir')
            ->assertNotified();

        $this->assertTrue($inventario->fresh()->isConcluido());
        $this->assertNotNull($inventario->fresh()->data_fim);

        // 2. Tentar bipar com inventário fechado não deve registrar
        $component->set('codigo_bipado', 'LIV999')
            ->call('biparLivro')
            ->assertNotified();

        $this->assertEquals(0, InventarioItem::where('inventario_id', $inventario->id)->count());

        // 3. Reabre auditoria
        $component->callAction('reabrir')
            ->assertNotified();

        $this->assertTrue($inventario->fresh()->isAberto());
        $this->assertNull($inventario->fresh()->data_fim);
    }

    public function test_calculo_de_livros_presentes_emprestados_e_faltantes(): void
    {
        $this->actingAs($this->superAdmin);
        session(['active_role' => 'super_admin']);

        $inventario = InventarioAcervo::create([
            'titulo' => 'Inventario Divergencias',
            'data_inicio' => now()->toDateString(),
            'status' => 'em_andamento',
            'user_id' => $this->superAdmin->id,
        ]);

        $livroPresente = Livro::factory()->create(['titulo' => 'Livro Presente', 'codigo' => 'PRESENTE']);
        $livroEmprestado = Livro::factory()->create(['titulo' => 'Livro Emprestado', 'codigo' => 'EMPRESTADO']);
        $livroFaltante = Livro::factory()->create(['titulo' => 'Livro Faltante', 'codigo' => 'FALTANTE']);

        // Bipa o presente
        InventarioItem::create([
            'inventario_id' => $inventario->id,
            'livro_id' => $livroPresente->id,
            'bipado_em' => now(),
            'quantidade_conferida' => 1,
            'user_id' => $this->superAdmin->id,
        ]);

        // Empresta o segundo
        $aluno = Pessoa::factory()->create();
        $turma = Turma::factory()->create();
        $matricula = Matricula::factory()->create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'situacao' => 'ativa',
        ]);

        Emprestimo::create([
            'livro_id' => $livroEmprestado->id,
            'matricula_id' => $matricula->id,
            'data_emprestimo' => now()->subDay()->toDateString(),
            'data_prevista_devolucao' => now()->addDays(5)->toDateString(),
            'status' => StatusEmprestimo::Emprestado,
        ]);

        $component = Livewire::test(ConferenciaInventario::class, ['record' => $inventario]);

        $this->assertEquals(3, $component->instance()->total_acervo);
        $this->assertEquals(1, $component->instance()->total_conferidos);
        $this->assertEquals(1, $component->instance()->total_emprestados);

        $faltantes = $component->instance()->livros_faltantes;
        $this->assertCount(1, $faltantes);
        $this->assertEquals($livroFaltante->id, $faltantes->first()->id);
    }
}
