<?php

namespace Tests\Feature;

use App\Filament\Resources\Unidades\Pages\CreateUnidade;
use App\Filament\Resources\Unidades\Pages\EditUnidade;
use App\Filament\Resources\Unidades\Pages\ListUnidades;
use App\Models\InstituicaoEnsino;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UnidadeFilamentTest extends TestCase
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

    public function test_usuario_sem_permissao_recebe_403_ao_acessar_unidades(): void
    {
        $this->actingAs($this->usuarioSemPermissao);
        session(['active_role' => 'sem_acesso']);

        $this->get('/admin/unidades')
            ->assertStatus(403);
    }

    public function test_admin_visualiza_listagem_de_unidades(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        $unidade = Unidade::create([
            'nome' => 'Unidade Centro',
            'cnpj' => '11.222.333/0001-44',
            'situacao_funcionamento' => '1',
        ]);

        Livewire::test(ListUnidades::class)
            ->assertCanSeeTableRecords([$unidade])
            ->assertSee('Unidade Centro');
    }

    public function test_admin_pode_criar_unidade(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        $instituicao = InstituicaoEnsino::create([
            'nome' => 'Instituição Mantenedora',
            'flag_ativo' => true,
        ]);

        Livewire::test(CreateUnidade::class)
            ->fillForm([
                'nome' => 'Unidade Zona Sul',
                'cnpj' => '55.666.777/0001-88',
                'situacao_funcionamento' => '1',
                'instituicao_ensino_id' => $instituicao->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('unidade', [
            'nome' => 'Unidade Zona Sul',
            'instituicao_ensino_id' => $instituicao->id,
        ]);
    }

    public function test_validacao_rejeita_unidade_sem_nome(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        Livewire::test(CreateUnidade::class)
            ->fillForm([
                'nome' => '',
            ])
            ->call('create')
            ->assertHasFormErrors([
                'nome' => 'required',
            ]);
    }

    public function test_admin_pode_editar_unidade(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        $unidade = Unidade::create([
            'nome' => 'Unidade Campi 1',
            'situacao_funcionamento' => '1',
        ]);

        Livewire::test(EditUnidade::class, ['record' => $unidade->id])
            ->fillForm([
                'nome' => 'Unidade Campi Principal',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $unidade->refresh();

        $this->assertEquals('Unidade Campi Principal', $unidade->nome);
    }
}
