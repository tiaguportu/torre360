<?php

namespace Tests\Feature;

use App\Enums\TemplateCrachaEntidade;
use App\Filament\Resources\TemplateCrachas\Pages\CreateTemplateCracha;
use App\Filament\Resources\TemplateCrachas\Pages\EditTemplateCracha;
use App\Filament\Resources\TemplateCrachas\Pages\ListTemplateCrachas;
use App\Models\TemplateCracha;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TemplateCrachaFilamentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $usuarioSemPermissao;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $roleSuper = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $roleSemAcesso = Role::firstOrCreate(['name' => 'sem_acesso', 'guard_name' => 'web']);

        $this->admin = User::factory()->create([
            'activated_at' => now()->subDay(),
            'email_verified_at' => now(),
        ]);
        $this->admin->assignRole($roleSuper);

        $this->usuarioSemPermissao = User::factory()->create([
            'activated_at' => now()->subDay(),
            'email_verified_at' => now(),
        ]);
        $this->usuarioSemPermissao->assignRole($roleSemAcesso);
    }

    public function test_usuario_sem_permissao_recebe_403_ao_acessar_templates_de_cracha(): void
    {
        $this->actingAs($this->usuarioSemPermissao);
        session(['active_role' => 'sem_acesso']);

        $this->get('/admin/template-crachas')
            ->assertStatus(403);
    }

    public function test_admin_visualiza_listagem_de_templates_de_cracha(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        $template = TemplateCracha::create([
            'nome' => 'Crachá de Estudante Padrão',
            'tipo_entidade' => TemplateCrachaEntidade::PESSOA,
            'largura' => 300,
            'altura' => 480,
            'orientacao' => 'vertical',
        ]);

        Livewire::test(ListTemplateCrachas::class)
            ->assertCanSeeTableRecords([$template])
            ->assertSee('Crachá de Estudante Padrão');
    }

    public function test_admin_pode_criar_template_de_cracha(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        Livewire::test(CreateTemplateCracha::class)
            ->fillForm([
                'nome' => 'Crachá Visitante / Eventos',
                'tipo_entidade' => TemplateCrachaEntidade::PESSOA->value,
                'largura' => 350,
                'altura' => 500,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('template_crachas', [
            'nome' => 'Crachá Visitante / Eventos',
            'largura' => 350,
            'altura' => 500,
        ]);
    }

    public function test_validacao_rejeita_cracha_sem_nome(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        Livewire::test(CreateTemplateCracha::class)
            ->fillForm([
                'nome' => '',
            ])
            ->call('create')
            ->assertHasFormErrors([
                'nome' => 'required',
            ]);
    }

    public function test_admin_pode_editar_template_de_cracha(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        $template = TemplateCracha::create([
            'nome' => 'Crachá Turma',
            'tipo_entidade' => TemplateCrachaEntidade::TURMA,
            'largura' => 300,
            'altura' => 480,
        ]);

        Livewire::test(EditTemplateCracha::class, ['record' => $template->id])
            ->fillForm([
                'nome' => 'Crachá Turma Ajustado',
                'largura' => 320,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $template->refresh();

        $this->assertEquals('Crachá Turma Ajustado', $template->nome);
        $this->assertEquals(320, $template->largura);
    }
}
