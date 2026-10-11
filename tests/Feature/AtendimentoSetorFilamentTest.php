<?php

namespace Tests\Feature;

use App\Filament\Resources\AtendimentoSetores\Pages\CreateAtendimentoSetor;
use App\Filament\Resources\AtendimentoSetores\Pages\EditAtendimentoSetor;
use App\Filament\Resources\AtendimentoSetores\Pages\ListAtendimentoSetores;
use App\Models\AtendimentoSetor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AtendimentoSetorFilamentTest extends TestCase
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

    public function test_usuario_sem_permissao_recebe_403_ao_acessar_setores_de_atendimento(): void
    {
        $this->actingAs($this->usuarioSemPermissao);
        session(['active_role' => 'sem_acesso']);

        $this->get('/admin/atendimento-setores')
            ->assertStatus(403);
    }

    public function test_admin_visualiza_listagem_de_setores(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        $setor = AtendimentoSetor::create([
            'nome' => 'Coordenação Geral',
            'descricao' => 'Dúvidas pedagógicas e rotina escolar',
            'email_notificacao' => 'coordenacao@escola.com',
            'ordem' => 1,
            'ativo' => true,
        ]);

        Livewire::test(ListAtendimentoSetores::class)
            ->assertCanSeeTableRecords([$setor])
            ->assertSee('Coordenação Geral')
            ->assertSee('coordenacao@escola.com');
    }

    public function test_admin_pode_criar_setor_de_atendimento(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        Livewire::test(CreateAtendimentoSetor::class)
            ->fillForm([
                'nome' => 'Tesouraria e Financeiro',
                'descricao' => 'Assuntos relativos a mensalidades e acordos',
                'email_notificacao' => 'financeiro@escola.com',
                'ordem' => 2,
                'ativo' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('atendimento_setores', [
            'nome' => 'Tesouraria e Financeiro',
            'email_notificacao' => 'financeiro@escola.com',
            'ativo' => true,
        ]);
    }

    public function test_validacao_rejeita_setor_sem_nome(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        Livewire::test(CreateAtendimentoSetor::class)
            ->fillForm([
                'nome' => '',
            ])
            ->call('create')
            ->assertHasFormErrors([
                'nome' => 'required',
            ]);
    }

    public function test_admin_pode_editar_setor_de_atendimento(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        $setor = AtendimentoSetor::create([
            'nome' => 'Secretaria Geral',
            'email_notificacao' => 'secretaria@antigo.com',
            'ordem' => 5,
            'ativo' => true,
        ]);

        Livewire::test(EditAtendimentoSetor::class, ['record' => $setor->id])
            ->fillForm([
                'nome' => 'Secretaria Acadêmica Integrada',
                'email_notificacao' => 'secretaria@novo.com',
                'ativo' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $setor->refresh();

        $this->assertEquals('Secretaria Acadêmica Integrada', $setor->nome);
        $this->assertEquals('secretaria@novo.com', $setor->email_notificacao);
        $this->assertFalse((bool) $setor->ativo);
    }
}
