<?php

namespace Tests\Feature;

use App\Enums\StatusEmprestimo;
use App\Filament\Resources\Emprestimos\Pages\CreateEmprestimo;
use App\Filament\Resources\Emprestimos\Pages\ListEmprestimos;
use App\Filament\Resources\Livros\Pages\ListLivros;
use App\Models\Emprestimo;
use App\Models\Livro;
use App\Models\Matricula;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BibliotecaTest extends TestCase
{
    use RefreshDatabase;

    private function autenticarComoAdmin(): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach (['Livro', 'Emprestimo'] as $modelo) {
            foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'] as $acao) {
                $permissao = Permission::firstOrCreate(['name' => "{$acao}:{$modelo}", 'guard_name' => 'web']);
                $role->givePermissionTo($permissao);
            }
        }

        $user = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $user->assignRole('admin');
        $this->actingAs($user);
        session(['active_role' => 'admin']);

        return $user;
    }

    public function test_pagina_de_livros_carrega(): void
    {
        $this->autenticarComoAdmin();
        Livro::factory()->count(3)->create();

        Livewire::test(ListLivros::class)
            ->assertSuccessful();
    }

    public function test_pagina_de_emprestimos_carrega(): void
    {
        $this->autenticarComoAdmin();
        Emprestimo::factory()->count(2)->create();

        Livewire::test(ListEmprestimos::class)
            ->assertSuccessful();
    }

    public function test_tem_exemplar_disponivel(): void
    {
        $livro = Livro::factory()->create(['quantidade_disponivel' => 0]);
        $this->assertFalse($livro->temExemplarDisponivel());

        $livro->update(['quantidade_disponivel' => 2]);
        $this->assertTrue($livro->fresh()->temExemplarDisponivel());
    }

    public function test_criar_emprestimo_decrementa_quantidade_disponivel(): void
    {
        $this->autenticarComoAdmin();
        $livro = Livro::factory()->create(['quantidade_disponivel' => 2]);
        $matricula = Matricula::factory()->create();

        Livewire::test(CreateEmprestimo::class)
            ->fillForm([
                'livro_id' => $livro->id,
                'matricula_id' => $matricula->id,
                'data_emprestimo' => now()->toDateString(),
                'data_prevista_devolucao' => now()->addDays(14)->toDateString(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, $livro->fresh()->quantidade_disponivel);
        $this->assertDatabaseHas('emprestimos', [
            'livro_id' => $livro->id,
            'matricula_id' => $matricula->id,
            'status' => 'emprestado',
        ]);
    }

    public function test_registrar_devolucao_incrementa_quantidade_e_muda_status(): void
    {
        $this->autenticarComoAdmin();
        $livro = Livro::factory()->create(['quantidade_disponivel' => 1]);
        $emprestimo = Emprestimo::factory()->create(['livro_id' => $livro->id, 'status' => 'emprestado']);

        Livewire::test(ListEmprestimos::class)
            ->callTableAction('devolver', $emprestimo)
            ->assertSuccessful();

        $this->assertSame(2, $livro->fresh()->quantidade_disponivel);
        $this->assertSame(StatusEmprestimo::Devolvido, $emprestimo->fresh()->status);
        $this->assertNotNull($emprestimo->fresh()->data_devolucao);
    }

    public function test_atualizar_atrasados_marca_emprestados_vencidos(): void
    {
        $vencido = Emprestimo::factory()->create(['status' => 'emprestado', 'data_prevista_devolucao' => now()->subDays(3)]);
        $futuro = Emprestimo::factory()->create(['status' => 'emprestado', 'data_prevista_devolucao' => now()->addDays(3)]);
        $jaDevolvido = Emprestimo::factory()->create(['status' => 'devolvido', 'data_prevista_devolucao' => now()->subDays(10)]);

        $total = Emprestimo::atualizarAtrasados();

        $this->assertSame(1, $total);
        $this->assertSame(StatusEmprestimo::Atrasado, $vencido->fresh()->status);
        $this->assertSame(StatusEmprestimo::Emprestado, $futuro->fresh()->status);
        $this->assertSame(StatusEmprestimo::Devolvido, $jaDevolvido->fresh()->status);
    }
}
