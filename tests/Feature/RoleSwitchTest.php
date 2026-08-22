<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin', 'professor'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_usuario_troca_para_papel_que_possui(): void
    {
        $user = User::factory()->create(['activated_at' => now()]);
        $user->assignRole(['admin', 'professor']);

        $response = $this->actingAs($user)
            ->withSession(['active_role' => 'admin'])
            ->get('/switch-role/professor');

        $response->assertRedirect(url('/admin'));
        $this->assertSame('professor', session('active_role'));
    }

    public function test_usuario_nao_consegue_trocar_para_papel_que_nao_possui(): void
    {
        $user = User::factory()->create(['activated_at' => now()]);
        $user->assignRole('admin');

        $response = $this->actingAs($user)
            ->withSession(['active_role' => 'admin'])
            ->get('/switch-role/professor');

        $response->assertRedirect(url('/admin'));
        $this->assertSame('admin', session('active_role'));
    }

    public function test_usuario_nao_autenticado_nao_consegue_trocar_de_papel(): void
    {
        $response = $this->get('/switch-role/admin');

        $response->assertRedirect(url('/admin/login'));
    }
}
