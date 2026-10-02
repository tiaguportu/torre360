<?php

namespace Tests\Feature;

use App\Filament\Resources\SubstituicaoProfessors\Pages\CreateSubstituicaoProfessor;
use App\Filament\Resources\SubstituicaoProfessors\Pages\ListSubstituicaoProfessors;
use App\Models\Pessoa;
use App\Models\SubstituicaoProfessor;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SubstituicaoProfessorTest extends TestCase
{
    use RefreshDatabase;

    private function autenticarComoAdmin(): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'] as $acao) {
            $permissao = Permission::firstOrCreate(['name' => "{$acao}:SubstituicaoProfessor", 'guard_name' => 'web']);
            $role->givePermissionTo($permissao);
        }

        $user = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $user->assignRole('admin');
        $this->actingAs($user);
        session(['active_role' => 'admin']);

        return $user;
    }

    public function test_pagina_de_listagem_carrega(): void
    {
        $this->autenticarComoAdmin();
        SubstituicaoProfessor::factory()->count(2)->create();

        Livewire::test(ListSubstituicaoProfessors::class)
            ->assertSuccessful();
    }

    public function test_is_ativa_true_sem_data_fim(): void
    {
        $substituicao = SubstituicaoProfessor::factory()->create(['data_fim' => null]);

        $this->assertTrue($substituicao->isAtiva());
    }

    public function test_is_ativa_false_com_data_fim_passada(): void
    {
        $substituicao = SubstituicaoProfessor::factory()->create(['data_fim' => now()->subDay()]);

        $this->assertFalse($substituicao->isAtiva());
    }

    public function test_cria_substituicao_via_formulario(): void
    {
        $this->autenticarComoAdmin();
        $turma = Turma::factory()->create();
        $titular = Pessoa::factory()->create(['nome' => 'Professor Titular']);
        $substituto = Pessoa::factory()->create(['nome' => 'Professor Substituto']);

        Livewire::test(CreateSubstituicaoProfessor::class)
            ->fillForm([
                'turma_id' => $turma->id,
                'professor_titular_id' => $titular->id,
                'professor_substituto_id' => $substituto->id,
                'data_inicio' => now()->toDateString(),
                'motivo' => 'Licença médica',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('substituicoes_professor', [
            'turma_id' => $turma->id,
            'professor_titular_id' => $titular->id,
            'professor_substituto_id' => $substituto->id,
        ]);
    }
}
