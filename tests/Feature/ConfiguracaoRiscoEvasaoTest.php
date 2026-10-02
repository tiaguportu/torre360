<?php

namespace Tests\Feature;

use App\Filament\Pages\ConfiguracaoRiscoEvasao;
use App\Models\RiscoEvasaoConfiguracao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConfiguracaoRiscoEvasaoTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $permissao = Permission::firstOrCreate(['name' => 'View:ConfiguracaoRiscoEvasao', 'guard_name' => 'web']);
        $role->givePermissionTo($permissao);

        $user = User::factory()->create(['activated_at' => now()]);
        $user->assignRole('super_admin');

        return $user;
    }

    public function test_pagina_carrega_com_os_valores_do_config(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/secretaria/pesos-risco-evasao')
            ->assertOk()
            ->assertSee('Pesos do Risco de Evasão');
    }

    public function test_salvar_persiste_e_aplica_os_novos_pesos(): void
    {
        $this->actingAs($this->admin());

        $valores = (new \ReflectionMethod(ConfiguracaoRiscoEvasao::class, 'valoresAtuais'))->invoke(new ConfiguracaoRiscoEvasao);
        $valores['pesos']['frequencia'] = 50;
        $valores['pesos']['desempenho'] = 35;
        $valores['pesos']['inadimplencia'] = 15;

        Livewire::test(ConfiguracaoRiscoEvasao::class)
            ->fillForm($valores, 'content')
            ->call('salvar')
            ->assertNotified('Configuração salva');

        $this->assertSame(50, config('risco_evasao.pesos.frequencia'));
        $this->assertSame(1, RiscoEvasaoConfiguracao::count());
    }

    public function test_soma_dos_pesos_diferente_de_100_e_recusada(): void
    {
        $this->actingAs($this->admin());

        $valores = (new \ReflectionMethod(ConfiguracaoRiscoEvasao::class, 'valoresAtuais'))->invoke(new ConfiguracaoRiscoEvasao);
        $valores['pesos']['frequencia'] = 90;

        Livewire::test(ConfiguracaoRiscoEvasao::class)
            ->fillForm($valores, 'content')
            ->call('salvar')
            ->assertNotified('Configuração inválida');

        $this->assertSame(0, RiscoEvasaoConfiguracao::count());
    }

    public function test_restaurar_padrao_remove_a_personalizacao(): void
    {
        $this->actingAs($this->admin());
        RiscoEvasaoConfiguracao::create(['valores' => ['faixas_cor' => ['alto' => 90, 'moderado' => 50]]]);
        RiscoEvasaoConfiguracao::limparCache();

        Livewire::test(ConfiguracaoRiscoEvasao::class)->call('restaurarPadrao');

        $this->assertSame(0, RiscoEvasaoConfiguracao::count());
        $this->assertSame(60, config('risco_evasao.faixas_cor.alto'));
    }
}
