<?php

namespace Tests\Feature;

use App\Filament\Resources\Questionarios\Pages\ResponderQuestionario;
use App\Filament\Widgets\QuestionariosPendentes;
use App\Models\Questionario;
use App\Models\QuestionarioAlvo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class QuestionariosWidgetTest extends TestCase
{
    use RefreshDatabase;

    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $permission = Permission::firstOrCreate(['name' => 'View:QuestionariosPendentes', 'guard_name' => 'web']);
        $this->role->givePermissionTo($permission);
    }

    private function createAuthorizedUser(): User
    {
        $user = User::factory()->create(['activated_at' => now()]);
        $user->assignRole($this->role);
        $this->actingAs($user);
        session(['active_role' => 'admin']);

        return $user;
    }

    #[Test]
    public function widget_nao_deve_exibir_se_nao_houver_questionarios_pendentes()
    {
        $this->createAuthorizedUser();

        // Sem questionários na base, canView deve retornar false
        $this->assertFalse(QuestionariosPendentes::canView());
    }

    #[Test]
    public function widget_deve_exibir_se_houver_questionario_elegivel()
    {
        $this->createAuthorizedUser();

        // Criar questionário ativo sem restrição de alvos
        Questionario::create([
            'titulo' => 'Questionário Geral',
            'descricao' => 'Teste geral',
            'is_ativo' => true,
            'is_anonimo' => false,
        ]);

        $this->assertTrue(QuestionariosPendentes::canView());
    }

    #[Test]
    public function widget_nao_deve_exibir_se_questionario_estiver_inativo()
    {
        $this->createAuthorizedUser();

        // Criar questionário inativo
        Questionario::create([
            'titulo' => 'Questionário Geral',
            'descricao' => 'Teste geral',
            'is_ativo' => false,
            'is_anonimo' => false,
        ]);

        $this->assertFalse(QuestionariosPendentes::canView());
    }

    #[Test]
    public function widget_nao_deve_exibir_se_periodo_de_aplicacao_estiver_vencido()
    {
        $this->createAuthorizedUser();

        // Criar questionário com fim de aplicação no passado
        Questionario::create([
            'titulo' => 'Questionário Vencido',
            'descricao' => 'Teste vencido',
            'is_ativo' => true,
            'is_anonimo' => false,
            'inicio_aplicacao' => now()->subDays(10),
            'fim_aplicacao' => now()->subDays(1),
        ]);

        $this->assertFalse(QuestionariosPendentes::canView());
    }

    #[Test]
    public function can_access_deve_permitir_acesso_se_usuario_for_do_publico_alvo()
    {
        $user = $this->createAuthorizedUser();

        $questionario = Questionario::create([
            'titulo' => 'Questionário Alvo',
            'descricao' => 'Teste com alvo',
            'is_ativo' => true,
            'is_anonimo' => false,
        ]);

        // Vincular ao usuário
        QuestionarioAlvo::create([
            'questionario_id' => $questionario->id,
            'alvo_type' => 'User',
            'alvo_id' => $user->id,
        ]);

        $this->assertTrue(ResponderQuestionario::canAccess(['record' => $questionario]));
    }

    #[Test]
    public function can_access_deve_negar_acesso_se_usuario_nao_for_do_publico_alvo()
    {
        $user = $this->createAuthorizedUser();
        $outroUser = User::factory()->create();

        $questionario = Questionario::create([
            'titulo' => 'Questionário Alvo Outro',
            'descricao' => 'Teste com outro alvo',
            'is_ativo' => true,
            'is_anonimo' => false,
        ]);

        // Vincular ao outro usuário
        QuestionarioAlvo::create([
            'questionario_id' => $questionario->id,
            'alvo_type' => 'User',
            'alvo_id' => $outroUser->id,
        ]);

        $this->assertFalse(ResponderQuestionario::canAccess(['record' => $questionario]));
    }

    #[Test]
    public function usuario_elegivel_deve_conseguir_acessar_a_rota_de_responder()
    {
        $user = $this->createAuthorizedUser();

        $questionario = Questionario::create([
            'titulo' => 'Questionário Alvo',
            'descricao' => 'Teste com alvo',
            'is_ativo' => true,
            'is_anonimo' => false,
        ]);

        QuestionarioAlvo::create([
            'questionario_id' => $questionario->id,
            'alvo_type' => 'User',
            'alvo_id' => $user->id,
        ]);

        $response = $this->get("/admin/questionarios/{$questionario->id}/responder");
        $response->assertStatus(200);
    }

    #[Test]
    public function usuario_elegivel_deve_receber_403_ao_tentar_acessar_a_listagem_sem_permissao_shield()
    {
        $user = User::factory()->create([
            'activated_at' => now(),
        ]);
        $this->actingAs($user);

        $questionario = Questionario::create([
            'titulo' => 'Questionário Alvo',
            'descricao' => 'Teste com alvo',
            'is_ativo' => true,
            'is_anonimo' => false,
        ]);

        QuestionarioAlvo::create([
            'questionario_id' => $questionario->id,
            'alvo_type' => 'User',
            'alvo_id' => $user->id,
        ]);

        // Usuário não tem permissão ViewAny:Questionario do Shield, deve dar 403 no index do resource
        $response = $this->get('/admin/questionarios');
        $response->assertStatus(403);
    }

    #[Test]
    public function usuario_elegivel_deve_receber_403_ao_tentar_acessar_a_visualizacao_sem_permissao_shield()
    {
        $user = User::factory()->create([
            'activated_at' => now(),
        ]);
        $this->actingAs($user);

        $questionario = Questionario::create([
            'titulo' => 'Questionário Alvo',
            'descricao' => 'Teste com alvo',
            'is_ativo' => true,
            'is_anonimo' => false,
        ]);

        QuestionarioAlvo::create([
            'questionario_id' => $questionario->id,
            'alvo_type' => 'User',
            'alvo_id' => $user->id,
        ]);

        // Usuário não tem permissão View:Questionario do Shield, deve dar 403 na página de detalhes administrativa do resource
        $response = $this->get("/admin/questionarios/{$questionario->id}");
        $response->assertStatus(403);
    }
}
