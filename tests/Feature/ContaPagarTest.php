<?php

namespace Tests\Feature;

use App\Enums\StatusContaPagar;
use App\Filament\Resources\ContaPagars\Pages\ListContaPagars;
use App\Models\Banco;
use App\Models\ContaPagar;
use App\Models\Fornecedor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ContaPagarTest extends TestCase
{
    use RefreshDatabase;

    private function autenticarComoAdmin(): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'] as $acao) {
            $permissao = Permission::firstOrCreate(['name' => "{$acao}:ContaPagar", 'guard_name' => 'web']);
            $role->givePermissionTo($permissao);
        }

        $user = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $user->assignRole('admin');
        $this->actingAs($user);
        session(['active_role' => 'admin']);

        return $user;
    }

    public function test_atualizar_atrasadas_marca_pendentes_vencidas(): void
    {
        $vencida = ContaPagar::factory()->create(['status' => 'pendente', 'vencimento' => now()->subDays(3)]);
        $futura = ContaPagar::factory()->create(['status' => 'pendente', 'vencimento' => now()->addDays(3)]);
        $jaPaga = ContaPagar::factory()->create(['status' => 'pago', 'vencimento' => now()->subDays(10)]);

        $total = ContaPagar::atualizarAtrasadas();

        $this->assertSame(1, $total);
        $this->assertSame(StatusContaPagar::Atrasado, $vencida->fresh()->status);
        $this->assertSame(StatusContaPagar::Pendente, $futura->fresh()->status);
        $this->assertSame(StatusContaPagar::Pago, $jaPaga->fresh()->status);
    }

    public function test_dias_atraso_calculado_corretamente(): void
    {
        $conta = ContaPagar::factory()->create(['status' => 'atrasado', 'vencimento' => now()->subDays(5)]);

        $this->assertSame(5, $conta->dias_atraso);
    }

    public function test_dias_atraso_zero_para_conta_paga(): void
    {
        $conta = ContaPagar::factory()->create(['status' => 'pago', 'vencimento' => now()->subDays(5)]);

        $this->assertSame(0, $conta->dias_atraso);
    }

    public function test_pagina_de_listagem_carrega(): void
    {
        $this->autenticarComoAdmin();
        ContaPagar::factory()->count(3)->create();

        Livewire::test(ListContaPagars::class)
            ->assertSuccessful();
    }

    public function test_dar_baixa_cria_transacao_e_marca_paga(): void
    {
        $this->autenticarComoAdmin();
        Banco::create(['nome' => 'Banco Teste', 'is_active' => true]);
        $fornecedor = Fornecedor::create(['razao_social' => 'Fornecedor Teste']);
        $conta = ContaPagar::factory()->create(['status' => 'pendente', 'valor' => 300, 'fornecedor_id' => $fornecedor->id]);

        Livewire::test(ListContaPagars::class)
            ->callTableAction('dar_baixa', $conta, data: [
                'banco_id' => Banco::first()->id,
                'data_pagamento' => now()->toDateString(),
            ])
            ->assertSuccessful();

        $conta = $conta->fresh();
        $this->assertSame(StatusContaPagar::Pago, $conta->status);
        $this->assertNotNull($conta->transacao_bancaria_id);
        $this->assertDatabaseHas('transacao_bancarias', [
            'id' => $conta->transacao_bancaria_id,
            'tipo' => 'saida',
            'valor' => 300,
            'fornecedor_id' => $fornecedor->id,
        ]);
    }
}
