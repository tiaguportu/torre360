<?php

namespace Tests\Feature;

use App\Filament\Resources\PeriodoLetivos\Pages\CreatePeriodoLetivo;
use App\Filament\Resources\PeriodoLetivos\Pages\EditPeriodoLetivo;
use App\Filament\Resources\PeriodoLetivos\Pages\ListPeriodoLetivos;
use App\Models\PeriodoLetivo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PeriodoLetivoFilamentTest extends TestCase
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

    public function test_usuario_sem_permissao_recebe_403_ao_acessar_periodos_letivos(): void
    {
        $this->actingAs($this->usuarioSemPermissao);
        session(['active_role' => 'sem_acesso']);

        $this->get('/admin/periodo-letivos')
            ->assertStatus(403);
    }

    public function test_admin_visualiza_listagem_de_periodos_letivos(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        $periodo = PeriodoLetivo::create([
            'nome' => 'Ano Letivo 2027',
            'data_inicio' => '2027-02-01',
            'data_fim' => '2027-12-15',
            'nota_aprovacao' => 7,
            'nota_recuperacao_minima' => 5,
        ]);

        Livewire::test(ListPeriodoLetivos::class)
            ->assertCanSeeTableRecords([$periodo])
            ->assertSee('Ano Letivo 2027');
    }

    public function test_admin_pode_criar_periodo_letivo(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        Livewire::test(CreatePeriodoLetivo::class)
            ->fillForm([
                'nome' => 'Ano Letivo 2028',
                'data_inicio' => '2028-02-01',
                'data_fim' => '2028-12-15',
                'nota_aprovacao' => 7.0,
                'nota_recuperacao_minima' => 5.0,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('periodo_letivo', [
            'nome' => 'Ano Letivo 2028',
        ]);
    }

    public function test_validacao_rejeita_periodo_sem_campos_obrigatorios(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        Livewire::test(CreatePeriodoLetivo::class)
            ->fillForm([
                'nome' => '',
                'data_inicio' => null,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'nome' => 'required',
                'data_inicio' => 'required',
            ]);
    }

    public function test_admin_pode_editar_periodo_letivo(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        $periodo = PeriodoLetivo::create([
            'nome' => 'Ano Letivo 2026 Regular',
            'data_inicio' => '2026-02-01',
            'data_fim' => '2026-12-15',
            'nota_aprovacao' => 7,
            'nota_recuperacao_minima' => 5,
        ]);

        Livewire::test(EditPeriodoLetivo::class, ['record' => $periodo->id])
            ->fillForm([
                'nome' => 'Ano Letivo 2026 Completo',
                'nota_aprovacao' => 6.5,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $periodo->refresh();

        $this->assertEquals('Ano Letivo 2026 Completo', $periodo->nome);
        $this->assertEquals(6.5, (float) $periodo->nota_aprovacao);
    }
}
