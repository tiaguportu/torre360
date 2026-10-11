<?php

namespace Tests\Feature;

use App\Filament\Resources\TipoBolsas\Pages\CreateTipoBolsa;
use App\Filament\Resources\TipoBolsas\Pages\EditTipoBolsa;
use App\Filament\Resources\TipoBolsas\Pages\ListTipoBolsas;
use App\Models\TipoBolsa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TipoBolsaFilamentTest extends TestCase
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

    public function test_usuario_sem_permissao_recebe_403_ao_acessar_tipos_de_bolsa(): void
    {
        $this->actingAs($this->usuarioSemPermissao);
        session(['active_role' => 'sem_acesso']);

        $this->get('/admin/tipo-bolsas')
            ->assertStatus(403);
    }

    public function test_admin_visualiza_listagem_de_tipos_de_bolsa(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        $bolsa = TipoBolsa::create([
            'nome' => 'Bolsa Mérito Acadêmico',
            'percentual_maximo' => 50,
            'exige_aprovacao' => true,
            'criterio_renovacao' => 'Média geral superior a 8,5.',
        ]);

        Livewire::test(ListTipoBolsas::class)
            ->assertCanSeeTableRecords([$bolsa])
            ->assertSee('Bolsa Mérito Acadêmico')
            ->assertSee('50%');
    }

    public function test_admin_pode_criar_tipo_de_bolsa_com_sucesso(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        Livewire::test(CreateTipoBolsa::class)
            ->fillForm([
                'nome' => 'Bolsa Irmãos',
                'percentual_maximo' => 20,
                'exige_aprovacao' => false,
                'criterio_renovacao' => 'Manter matrícula ativa de ambos os dependentes.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('tipo_bolsas', [
            'nome' => 'Bolsa Irmãos',
            'percentual_maximo' => 20,
            'exige_aprovacao' => false,
        ]);
    }

    public function test_validacao_rejeita_tipo_de_bolsa_com_percentual_invalido(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        Livewire::test(CreateTipoBolsa::class)
            ->fillForm([
                'nome' => '',
                'percentual_maximo' => 150,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'nome' => 'required',
                'percentual_maximo',
            ]);
    }

    public function test_admin_pode_editar_tipo_de_bolsa(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        $bolsa = TipoBolsa::create([
            'nome' => 'Desconto Comercial Inicial',
            'percentual_maximo' => 15,
            'exige_aprovacao' => false,
        ]);

        Livewire::test(EditTipoBolsa::class, ['record' => $bolsa->id])
            ->fillForm([
                'nome' => 'Desconto Comercial Atualizado',
                'percentual_maximo' => 25,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $bolsa->refresh();

        $this->assertEquals('Desconto Comercial Atualizado', $bolsa->nome);
        $this->assertEquals(25, $bolsa->percentual_maximo);
    }
}
