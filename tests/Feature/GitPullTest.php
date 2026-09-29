<?php

namespace Tests\Feature;

use App\Filament\Pages\GitPull;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GitPullTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'professor', 'guard_name' => 'web']);
    }

    public function test_usuario_comum_nao_pode_acessar_git_pull(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professor');

        $this->actingAs($user);
        $this->assertFalse(GitPull::canAccess());

        $response = $this->get('/admin/git-pull');
        $response->assertStatus(403);
    }

    public function test_super_admin_pode_acessar_e_executar_migracoes_e_limpar_cache(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $this->actingAs($superAdmin);
        $this->assertTrue(GitPull::canAccess());

        Livewire::test(GitPull::class)
            ->call('runMigrate')
            ->assertNotified('Migrações Executadas com Sucesso')
            ->call('runOptimizeClear')
            ->assertNotified('Caches Limpos com Sucesso');
    }
}
