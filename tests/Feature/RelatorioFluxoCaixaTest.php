<?php

namespace Tests\Feature;

use App\Filament\Pages\RelatorioFluxoCaixa;
use App\Models\Banco;
use App\Models\TransacaoBancaria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RelatorioFluxoCaixaTest extends TestCase
{
    use RefreshDatabase;

    private function autenticarSuperAdmin(): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $user = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $user->assignRole('super_admin');
        $this->actingAs($user);
        session(['active_role' => 'super_admin']);

        return $user;
    }

    public function test_pagina_carrega_e_agrega_por_mes(): void
    {
        $this->autenticarSuperAdmin();
        $banco = Banco::create(['nome' => 'Banco Teste', 'is_active' => true]);

        TransacaoBancaria::create([
            'banco_id' => $banco->id,
            'tipo' => 'entrada',
            'valor' => 1000,
            'data_transacao' => now()->startOfMonth()->toDateString(),
            'conciliado' => true,
        ]);

        TransacaoBancaria::create([
            'banco_id' => $banco->id,
            'tipo' => 'saida',
            'valor' => 400,
            'data_transacao' => now()->startOfMonth()->toDateString(),
            'conciliado' => true,
        ]);

        $component = Livewire::test(RelatorioFluxoCaixa::class)->assertSuccessful();

        $resumo = $component->instance()->getResumo();

        $this->assertEquals(1000.0, $resumo['total_entradas']);
        $this->assertEquals(400.0, $resumo['total_saidas']);
        $this->assertEquals(600.0, $resumo['saldo_periodo']);
    }

    public function test_fluxo_mensal_ignora_transacoes_fora_do_periodo(): void
    {
        $this->autenticarSuperAdmin();
        $banco = Banco::create(['nome' => 'Banco Teste', 'is_active' => true]);

        TransacaoBancaria::create([
            'banco_id' => $banco->id,
            'tipo' => 'entrada',
            'valor' => 1000,
            'data_transacao' => now()->subMonths(20)->toDateString(),
            'conciliado' => true,
        ]);

        $resumo = Livewire::test(RelatorioFluxoCaixa::class)->instance()->getResumo();

        $this->assertEquals(0.0, $resumo['total_entradas']);
    }

    public function test_fluxo_mensal_retorna_doze_linhas(): void
    {
        $this->autenticarSuperAdmin();

        $linhas = Livewire::test(RelatorioFluxoCaixa::class)->instance()->getFluxoMensal();

        $this->assertCount(12, $linhas);
    }
}
