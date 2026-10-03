<?php

namespace Tests\Feature;

use App\Filament\Portal\Pages\Materiais;
use App\Filament\Resources\MaterialAulas\Pages\CreateMaterialAula;
use App\Filament\Resources\MaterialAulas\Pages\ListMaterialAulas;
use App\Models\Disciplina;
use App\Models\MaterialAula;
use App\Models\Matricula;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MaterialAulaTest extends TestCase
{
    use RefreshDatabase;

    private function autenticarComoAdmin(): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'] as $acao) {
            $permissao = Permission::firstOrCreate(['name' => "{$acao}:MaterialAula", 'guard_name' => 'web']);
            $role->givePermissionTo($permissao);
        }

        $user = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $user->assignRole('admin');
        $this->actingAs($user);
        session(['active_role' => 'admin']);

        return $user;
    }

    public function test_pagina_de_listagem_admin_carrega(): void
    {
        $this->autenticarComoAdmin();
        MaterialAula::factory()->count(2)->create();

        Livewire::test(ListMaterialAulas::class)
            ->assertSuccessful();
    }

    public function test_criar_material_do_tipo_link_via_formulario(): void
    {
        $this->autenticarComoAdmin();
        $turma = Turma::factory()->create();
        $disciplina = Disciplina::factory()->create();

        Livewire::test(CreateMaterialAula::class)
            ->fillForm([
                'titulo' => 'Introdução à Álgebra',
                'turma_id' => $turma->id,
                'disciplina_id' => $disciplina->id,
                'data_publicacao' => now()->toDateString(),
                'tipo' => 'link',
                'url' => 'https://exemplo.com/material',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('material_aulas', [
            'titulo' => 'Introdução à Álgebra',
            'turma_id' => $turma->id,
            'tipo' => 'link',
            'url' => 'https://exemplo.com/material',
        ]);
    }

    public function test_criar_material_do_tipo_link_sem_url_falha_validacao(): void
    {
        $this->autenticarComoAdmin();
        $turma = Turma::factory()->create();
        $disciplina = Disciplina::factory()->create();

        Livewire::test(CreateMaterialAula::class)
            ->fillForm([
                'titulo' => 'Sem URL',
                'turma_id' => $turma->id,
                'disciplina_id' => $disciplina->id,
                'data_publicacao' => now()->toDateString(),
                'tipo' => 'link',
                'url' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['url']);
    }

    public function test_url_acesso_retorna_a_url_direta_para_video_e_link(): void
    {
        $material = MaterialAula::factory()->create(['tipo' => 'video', 'url' => 'https://exemplo.com/video']);

        $this->assertSame('https://exemplo.com/video', $material->url_acesso);
    }

    public function test_url_acesso_resolve_o_storage_para_apostila(): void
    {
        Storage::fake('local');
        $material = MaterialAula::factory()->create(['tipo' => 'apostila', 'url' => null, 'arquivo_path' => 'materiais-aula/apostila.pdf']);

        $this->assertSame(Storage::url('materiais-aula/apostila.pdf'), $material->url_acesso);
    }

    public function test_url_acesso_nula_para_apostila_sem_arquivo(): void
    {
        $material = MaterialAula::factory()->create(['tipo' => 'apostila', 'url' => null, 'arquivo_path' => null]);

        $this->assertNull($material->url_acesso);
    }

    private function criarMatriculaComUsuario(?Turma $turma = null): array
    {
        $turma ??= Turma::factory()->create();
        $matricula = Matricula::factory()->create(['turma_id' => $turma->id, 'situacao' => 'ativa']);

        $user = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $user->pessoas()->attach($matricula->pessoa_id);

        return ['user' => $user, 'matricula' => $matricula, 'turma' => $turma];
    }

    public function test_portal_lista_apenas_materiais_visiveis_da_turma_do_aluno(): void
    {
        $dados = $this->criarMatriculaComUsuario();

        $materialVisivel = MaterialAula::factory()->create(['turma_id' => $dados['turma']->id, 'visivel' => true, 'titulo' => 'Material Visível']);
        MaterialAula::factory()->create(['turma_id' => $dados['turma']->id, 'visivel' => false, 'titulo' => 'Material Oculto']);
        MaterialAula::factory()->create(['visivel' => true, 'titulo' => 'Material de Outra Turma']);

        Livewire::actingAs($dados['user'])
            ->test(Materiais::class)
            ->assertCanSeeTableRecords([$materialVisivel])
            ->assertSee('Material Visível')
            ->assertDontSee('Material Oculto')
            ->assertDontSee('Material de Outra Turma');
    }

    public function test_acao_abrir_aponta_para_a_url_de_acesso(): void
    {
        $dados = $this->criarMatriculaComUsuario();
        $material = MaterialAula::factory()->create(['turma_id' => $dados['turma']->id, 'visivel' => true, 'tipo' => 'link', 'url' => 'https://exemplo.com/abrir']);

        Livewire::actingAs($dados['user'])
            ->test(Materiais::class)
            ->assertTableActionHasUrl('abrir', 'https://exemplo.com/abrir', record: $material);
    }
}
