<?php

namespace Tests\Feature;

use App\Models\Pessoa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PortalPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['super_admin', 'admin', 'aluno', 'responsavel'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_conta_de_equipe_e_redirecionada_do_portal_para_o_admin(): void
    {
        $user = User::factory()->create(['activated_at' => now()]);
        $user->assignRole('super_admin');

        $response = $this->actingAs($user)->get('/portal');

        $response->assertRedirect(url('/admin'));
    }

    public function test_aluno_acessa_o_portal_normalmente(): void
    {
        $user = User::factory()->create(['activated_at' => now()]);
        $user->assignRole('aluno');

        $pessoa = Pessoa::factory()->create();
        $pessoa->users()->save($user);

        $response = $this->actingAs($user)->get('/portal');

        $response->assertOk();
    }

    public function test_usuario_nao_autenticado_e_redirecionado_ao_login_do_portal(): void
    {
        $response = $this->get('/portal');

        $response->assertRedirect(url('/portal/login'));
    }
}
