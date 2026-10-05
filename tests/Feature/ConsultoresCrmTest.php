<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Quem pode ser consultor responsável por um lead (`User::consultoresCrm()`) e o escopo de contas ativas
 * (`User::ativos()`): `is_active` é um acessor calculado, não uma coluna.
 */
class ConsultoresCrmTest extends TestCase
{
    use RefreshDatabase;

    private Permission $permissao;

    protected function setUp(): void
    {
        parent::setUp();

        $this->permissao = Permission::firstOrCreate(['name' => 'Update:Interessado', 'guard_name' => 'web']);
    }

    private function usuario(array $atributos = []): User
    {
        return User::factory()->create($atributos + ['activated_at' => now()->subMonth()]);
    }

    /**
     * @return list<int>
     */
    private function idsConsultores(): array
    {
        return User::consultoresCrm()->pluck('id')->all();
    }

    public function test_inclui_permissao_direta_permissao_por_papel_e_admins(): void
    {
        $direta = $this->usuario();
        $direta->givePermissionTo($this->permissao);

        $papel = Role::firstOrCreate(['name' => 'secretaria', 'guard_name' => 'web']);
        $papel->givePermissionTo($this->permissao);
        $porPapel = $this->usuario();
        $porPapel->assignRole($papel);

        $admin = $this->usuario();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));

        $superAdmin = $this->usuario();
        $superAdmin->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));

        $ids = $this->idsConsultores();

        foreach ([$direta, $porPapel, $admin, $superAdmin] as $esperado) {
            $this->assertContains($esperado->id, $ids);
        }
    }

    public function test_exclui_contas_de_familias_e_papeis_sem_a_permissao(): void
    {
        $responsavel = $this->usuario();
        $responsavel->assignRole(Role::firstOrCreate(['name' => 'responsavel', 'guard_name' => 'web']));

        $professor = $this->usuario();
        $professor->assignRole(Role::firstOrCreate(['name' => 'professor', 'guard_name' => 'web']));

        $semNada = $this->usuario();

        $ids = $this->idsConsultores();

        foreach ([$responsavel, $professor, $semNada] as $excluido) {
            $this->assertNotContains($excluido->id, $ids);
        }
    }

    public function test_exclui_contas_nao_ativadas_ou_desativadas(): void
    {
        $naoAtivada = $this->usuario(['activated_at' => null]);
        $naoAtivada->givePermissionTo($this->permissao);

        $ativacaoFutura = $this->usuario(['activated_at' => now()->addDay()]);
        $ativacaoFutura->givePermissionTo($this->permissao);

        $desativada = $this->usuario(['deactivated_at' => now()->subDay()]);
        $desativada->givePermissionTo($this->permissao);

        $comDesativacaoFutura = $this->usuario(['deactivated_at' => now()->addMonth()]);
        $comDesativacaoFutura->givePermissionTo($this->permissao);

        $ids = $this->idsConsultores();

        $this->assertNotContains($naoAtivada->id, $ids);
        $this->assertNotContains($ativacaoFutura->id, $ids);
        $this->assertNotContains($desativada->id, $ids);
        $this->assertContains($comDesativacaoFutura->id, $ids);
    }

    public function test_escopo_ativos_equivale_ao_acessor_is_active(): void
    {
        $ativo = $this->usuario();
        $inativo = $this->usuario(['activated_at' => null]);

        $ids = User::ativos()->pluck('id')->all();

        $this->assertContains($ativo->id, $ids);
        $this->assertNotContains($inativo->id, $ids);
        $this->assertTrue($ativo->is_active);
        $this->assertFalse($inativo->is_active);
    }

    public function test_permissao_exigida_e_configuravel(): void
    {
        config(['crm.permissao_consultor' => 'Create:Interessado']);

        $com = $this->usuario();
        $com->givePermissionTo(Permission::firstOrCreate(['name' => 'Create:Interessado', 'guard_name' => 'web']));

        $apenasUpdate = $this->usuario();
        $apenasUpdate->givePermissionTo($this->permissao);

        $ids = $this->idsConsultores();

        $this->assertContains($com->id, $ids);
        $this->assertNotContains($apenasUpdate->id, $ids);
    }
}
