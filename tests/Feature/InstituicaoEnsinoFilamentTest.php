<?php

namespace Tests\Feature;

use App\Filament\Resources\InstituicaoEnsinos\Pages\CreateInstituicaoEnsino;
use App\Filament\Resources\InstituicaoEnsinos\Pages\EditInstituicaoEnsino;
use App\Filament\Resources\InstituicaoEnsinos\Pages\ListInstituicaoEnsinos;
use App\Models\InstituicaoEnsino;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class InstituicaoEnsinoFilamentTest extends TestCase
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

    public function test_usuario_sem_permissao_recebe_403_ao_acessar_instituicoes_de_ensino(): void
    {
        $this->actingAs($this->usuarioSemPermissao);
        session(['active_role' => 'sem_acesso']);

        $this->get('/admin/instituicao-ensinos')
            ->assertStatus(403);
    }

    public function test_admin_visualiza_listagem_de_instituicoes_de_ensino(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        $instituicao = InstituicaoEnsino::create([
            'nome' => 'Colégio Torre de Educação Integrada',
            'cnpj' => '12.345.678/0001-99',
            'codigo_inep' => '12345678',
            'flag_ativo' => true,
        ]);

        Livewire::test(ListInstituicaoEnsinos::class)
            ->assertCanSeeTableRecords([$instituicao])
            ->assertSee('Colégio Torre de Educação Integrada');
    }

    public function test_admin_pode_criar_instituicao_de_ensino(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        Livewire::test(CreateInstituicaoEnsino::class)
            ->fillForm([
                'nome' => 'Instituto Pedagógico Torre360',
                'cnpj' => '98.765.432/0001-11',
                'codigo_inep' => '87654321',
                'flag_ativo' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('instituicao_ensinos', [
            'nome' => 'Instituto Pedagógico Torre360',
            'codigo_inep' => '87654321',
        ]);
    }

    public function test_validacao_rejeita_instituicao_sem_nome(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        Livewire::test(CreateInstituicaoEnsino::class)
            ->fillForm([
                'nome' => '',
            ])
            ->call('create')
            ->assertHasFormErrors([
                'nome' => 'required',
            ]);
    }

    public function test_admin_pode_editar_instituicao_de_ensino(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        $instituicao = InstituicaoEnsino::create([
            'nome' => 'Colégio Torre Matriz',
            'flag_ativo' => true,
        ]);

        Livewire::test(EditInstituicaoEnsino::class, ['record' => $instituicao->id])
            ->fillForm([
                'nome' => 'Colégio Torre Sede Oficial',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $instituicao->refresh();

        $this->assertEquals('Colégio Torre Sede Oficial', $instituicao->nome);
    }
}
