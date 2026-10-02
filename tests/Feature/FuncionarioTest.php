<?php

namespace Tests\Feature;

use App\Enums\StatusPeriodoFerias;
use App\Filament\Resources\Funcionarios\Pages\EditFuncionario;
use App\Filament\Resources\Funcionarios\Pages\ListFuncionarios;
use App\Filament\Resources\Funcionarios\RelationManagers\ContratosTrabalhoRelationManager;
use App\Filament\Resources\Funcionarios\RelationManagers\PeriodosFeriasRelationManager;
use App\Models\ContratoTrabalho;
use App\Models\Funcionario;
use App\Models\PeriodoFerias;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FuncionarioTest extends TestCase
{
    use RefreshDatabase;

    private function autenticarComoAdmin(): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach (['Funcionario', 'ContratoTrabalho', 'PeriodoFerias'] as $modelo) {
            foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'] as $acao) {
                $permissao = Permission::firstOrCreate(['name' => "{$acao}:{$modelo}", 'guard_name' => 'web']);
                $role->givePermissionTo($permissao);
            }
        }

        $user = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $user->assignRole('admin');
        $this->actingAs($user);
        session(['active_role' => 'admin']);

        return $user;
    }

    public function test_pagina_de_listagem_carrega(): void
    {
        $this->autenticarComoAdmin();
        Funcionario::factory()->count(3)->create();

        Livewire::test(ListFuncionarios::class)
            ->assertSuccessful();
    }

    public function test_is_ativo_true_sem_data_desligamento(): void
    {
        $funcionario = Funcionario::factory()->create(['data_desligamento' => null]);

        $this->assertTrue($funcionario->isAtivo());
    }

    public function test_is_ativo_false_com_data_desligamento(): void
    {
        $funcionario = Funcionario::factory()->create(['data_desligamento' => now()->subDay()]);

        $this->assertFalse($funcionario->isAtivo());
    }

    public function test_contrato_atual_retorna_vigencia_sem_fim(): void
    {
        $funcionario = Funcionario::factory()->create();
        $antigo = ContratoTrabalho::factory()->create([
            'funcionario_id' => $funcionario->id,
            'vigencia_inicio' => now()->subYears(2),
            'vigencia_fim' => now()->subYear(),
            'salario' => 2000,
        ]);
        $atual = ContratoTrabalho::factory()->create([
            'funcionario_id' => $funcionario->id,
            'vigencia_inicio' => now()->subYear(),
            'vigencia_fim' => null,
            'salario' => 2500,
        ]);

        $this->assertSame($atual->id, $funcionario->fresh()->contratoAtual()->id);
    }

    public function test_registrar_aditivo_encerra_vigencia_e_cria_nova(): void
    {
        $funcionario = Funcionario::factory()->create();
        $contrato = ContratoTrabalho::factory()->create([
            'funcionario_id' => $funcionario->id,
            'vigencia_inicio' => now()->subYear()->toDateString(),
            'vigencia_fim' => null,
            'salario' => 2000,
        ]);

        $novo = $contrato->registrarAditivo('2800', now()->toDateString());

        $this->assertNotNull($contrato->fresh()->vigencia_fim);
        $this->assertSame('2800.00', $novo->salario);
        $this->assertNull($novo->vigencia_fim);
    }

    public function test_relation_manager_de_contratos_registra_aditivo(): void
    {
        $this->autenticarComoAdmin();
        $funcionario = Funcionario::factory()->create();
        $contrato = ContratoTrabalho::factory()->create([
            'funcionario_id' => $funcionario->id,
            'vigencia_inicio' => now()->subYear()->toDateString(),
            'vigencia_fim' => null,
            'salario' => 2000,
        ]);

        Livewire::test(ContratosTrabalhoRelationManager::class, [
            'ownerRecord' => $funcionario,
            'pageClass' => EditFuncionario::class,
        ])
            ->callTableAction('aditivo', $contrato, data: [
                'novo_salario' => '3000',
                'data_aditivo' => now()->toDateString(),
            ])
            ->assertSuccessful();

        $this->assertNotNull($contrato->fresh()->vigencia_fim);
        $this->assertDatabaseHas('contratos_trabalho', [
            'funcionario_id' => $funcionario->id,
            'salario' => '3000.00',
            'vigencia_fim' => null,
        ]);
    }

    public function test_relation_manager_de_ferias_registra_gozo(): void
    {
        $this->autenticarComoAdmin();
        $funcionario = Funcionario::factory()->create();
        $periodo = PeriodoFerias::factory()->create([
            'funcionario_id' => $funcionario->id,
            'dias_direito' => 30,
            'dias_gozados' => 0,
        ]);

        Livewire::test(PeriodosFeriasRelationManager::class, [
            'ownerRecord' => $funcionario,
            'pageClass' => EditFuncionario::class,
        ])
            ->callTableAction('registrar_gozo', $periodo, data: [
                'data_inicio_gozo' => now()->toDateString(),
                'data_fim_gozo' => now()->addDays(15)->toDateString(),
                'dias' => 15,
            ])
            ->assertSuccessful();

        $periodo = $periodo->fresh();
        $this->assertSame(15, $periodo->dias_gozados);
        $this->assertSame(StatusPeriodoFerias::Parcial, $periodo->status);
    }
}
