<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_usuario_nao_autenticado_e_redirecionado_ao_login_do_admin(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect(url('/admin/login'));
    }

    public function test_super_admin_acessa_o_dashboard_do_admin(): void
    {
        $user = User::factory()->create(['activated_at' => now()]);
        $user->assignRole('super_admin');

        $response = $this->actingAs($user)->get('/admin');

        $response->assertOk();
    }

    public function test_usuario_desativado_nao_acessa_o_admin(): void
    {
        $user = User::factory()->create([
            'activated_at' => now()->subDays(30),
            'deactivated_at' => now()->subDay(),
        ]);
        $user->assignRole('super_admin');

        $response = $this->actingAs($user)->get('/admin');

        $response->assertForbidden();
    }

    public function test_usuario_ainda_nao_ativado_nao_acessa_o_admin(): void
    {
        $user = User::factory()->create(['activated_at' => null]);
        $user->assignRole('super_admin');

        $response = $this->actingAs($user)->get('/admin');

        $response->assertForbidden();
    }
}
