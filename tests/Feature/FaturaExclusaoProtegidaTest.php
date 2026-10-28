<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatusFatura;
use App\Filament\Resources\Contratos\Pages\EditContrato;
use App\Filament\Resources\Contratos\RelationManagers\FaturasRelationManager;
use App\Models\Banco;
use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\ItemFatura;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\TransacaoBancaria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FaturaExclusaoProtegidaTest extends TestCase
{
    use RefreshDatabase;

    private function autenticarSuperAdmin(): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'ViewAny:Fatura', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Delete:Fatura', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'Update:Fatura', 'guard_name' => 'web']);

        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $role->givePermissionTo(['ViewAny:Fatura', 'Delete:Fatura', 'Update:Fatura']);

        $user = User::factory()->create([
            'activated_at' => now()->subDay(),
            'email_verified_at' => now(),
        ]);
        $user->assignRole($role);
        $this->actingAs($user);
        session(['active_role' => 'super_admin']);

        return $user;
    }

    private function createContratoComAluno(array $contratoAttributes = []): Contrato
    {
        $aluno = Pessoa::factory()->create(['nome' => 'José Saramago']);
        $matricula = Matricula::factory()->create(['pessoa_id' => $aluno->id]);

        return Contrato::create(array_merge([
            'matricula_id' => $matricula->id,
            'valor_total' => 6000.00,
            'data_aceite' => '2026-01-10',
        ], $contratoAttributes));
    }

    public function test_fatura_pendente_comum_pode_ser_excluida_e_reseta_assinafy(): void
    {
        $this->autenticarSuperAdmin();

        $contrato = $this->createContratoComAluno([
            'assinafy_id' => 'doc_assinafy_exclusao_1',
            'assinafy_status' => 'pending',
        ]);

        $fatura = Fatura::create([
            'contrato_id' => $contrato->id,
            'vencimento' => '2026-03-10',
            'status' => StatusFatura::Pendente,
        ]);
        ItemFatura::create([
            'fatura_id' => $fatura->id,
            'descricao' => 'Parcela Normal',
            'valor_unitario' => 500.00,
            'quantidade' => 1,
        ]);

        Livewire::test(FaturasRelationManager::class, [
            'ownerRecord' => $contrato,
            'pageClass' => EditContrato::class,
        ])
            ->assertTableActionVisible('delete', $fatura)
            ->callTableAction('delete', $fatura);

        $this->assertDatabaseMissing('faturas', ['id' => $fatura->id]);
        $this->assertFalse($contrato->fresh()->jaEnviadoAssinafy());
    }

    public function test_fatura_com_transacao_bancaria_tem_delete_oculto_e_bloqueado(): void
    {
        $this->autenticarSuperAdmin();
        $banco = Banco::create(['codigo' => '001', 'nome' => 'Banco do Brasil']);

        $contrato = $this->createContratoComAluno();

        $faturaPaga = Fatura::create([
            'contrato_id' => $contrato->id,
            'vencimento' => '2026-03-10',
            'status' => StatusFatura::Pago,
        ]);
        ItemFatura::create([
            'fatura_id' => $faturaPaga->id,
            'descricao' => 'Parcela Paga',
            'valor_unitario' => 800.00,
            'quantidade' => 1,
        ]);
        TransacaoBancaria::create([
            'fatura_id' => $faturaPaga->id,
            'banco_id' => $banco->id,
            'valor' => 800.00,
            'tipo' => 'entrada',
            'data_transacao' => '2026-03-09',
        ]);

        Livewire::test(FaturasRelationManager::class, [
            'ownerRecord' => $contrato,
            'pageClass' => EditContrato::class,
        ])
            ->assertTableActionHidden('delete', $faturaPaga);

        $this->assertDatabaseHas('faturas', ['id' => $faturaPaga->id]);
    }

    public function test_fatura_com_gateway_id_tem_delete_oculto(): void
    {
        $this->autenticarSuperAdmin();

        $contrato = $this->createContratoComAluno();

        $faturaGateway = Fatura::create([
            'contrato_id' => $contrato->id,
            'vencimento' => '2026-04-10',
            'status' => StatusFatura::Pendente,
            'gateway_id' => 'gw_cobranca_ativa_123',
        ]);
        ItemFatura::create([
            'fatura_id' => $faturaGateway->id,
            'descricao' => 'Parcela Gateway',
            'valor_unitario' => 600.00,
            'quantidade' => 1,
        ]);

        Livewire::test(FaturasRelationManager::class, [
            'ownerRecord' => $contrato,
            'pageClass' => EditContrato::class,
        ])
            ->assertTableActionHidden('delete', $faturaGateway);

        $this->assertDatabaseHas('faturas', ['id' => $faturaGateway->id]);
    }

    public function test_fatura_parcial_ou_paga_sem_transacao_tem_delete_oculto(): void
    {
        $this->autenticarSuperAdmin();

        $contrato = $this->createContratoComAluno();

        $faturaParcial = Fatura::create([
            'contrato_id' => $contrato->id,
            'vencimento' => '2026-05-10',
            'status' => StatusFatura::Parcial,
        ]);
        ItemFatura::create([
            'fatura_id' => $faturaParcial->id,
            'descricao' => 'Parcela Parcial',
            'valor_unitario' => 700.00,
            'quantidade' => 1,
        ]);

        Livewire::test(FaturasRelationManager::class, [
            'ownerRecord' => $contrato,
            'pageClass' => EditContrato::class,
        ])
            ->assertTableActionHidden('delete', $faturaParcial);

        $this->assertDatabaseHas('faturas', ['id' => $faturaParcial->id]);
    }

    public function test_model_hook_deleting_lanca_logic_exception_em_fatura_protegida(): void
    {
        $contrato = $this->createContratoComAluno();

        $fatura = Fatura::create([
            'contrato_id' => $contrato->id,
            'vencimento' => '2026-06-10',
            'status' => StatusFatura::Pago,
        ]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('possui pagamentos, registro em gateway ou status liquidado e não pode ser excluída');

        $fatura->delete();
    }

    public function test_policy_delete_retorna_false_para_fatura_protegida(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'Delete:Fatura', 'guard_name' => 'web']);

        $role = Role::firstOrCreate(['name' => 'financeiro_com_delete', 'guard_name' => 'web']);
        $role->givePermissionTo(['Delete:Fatura']);

        $user = User::factory()->create();
        $user->assignRole($role);

        $contrato = $this->createContratoComAluno();

        $faturaProtegida = Fatura::create([
            'contrato_id' => $contrato->id,
            'vencimento' => '2026-07-10',
            'status' => StatusFatura::Pago,
        ]);

        $faturaPendente = Fatura::create([
            'contrato_id' => $contrato->id,
            'vencimento' => '2026-08-10',
            'status' => StatusFatura::Pendente,
        ]);

        $this->actingAs($user);
        session(['active_role' => 'financeiro_com_delete']);

        $this->assertFalse($user->can('delete', $faturaProtegida));
        $this->assertTrue($user->can('delete', $faturaPendente));
    }
}
