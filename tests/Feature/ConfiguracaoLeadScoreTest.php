<?php

namespace Tests\Feature;

use App\Filament\Pages\ConfiguracaoLeadScore;
use App\Models\LeadScoreConfiguracao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConfiguracaoLeadScoreTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::factory()->create(['activated_at' => now()]);
        $user->assignRole('super_admin');

        return $user;
    }

    public function test_pagina_carrega_com_os_valores_do_config(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/crm/pesos-lead-score')
            ->assertOk()
            ->assertSee('Pesos do Lead Score');
    }

    public function test_salvar_persiste_e_aplica_os_novos_pesos(): void
    {
        $this->actingAs($this->admin());

        $state = (new \ReflectionMethod(ConfiguracaoLeadScore::class, 'valoresAtuais'));
        $pagina = Livewire::test(ConfiguracaoLeadScore::class);
        $valores = $state->invoke(new ConfiguracaoLeadScore);

        $valores['pesos']['distancia'] = 15;
        $valores['pesos']['estagio_funil'] = 0;

        $pagina->fillForm($valores, 'content')->call('salvar')->assertNotified('Configuração salva');

        $this->assertSame(15, config('lead_score.pesos.distancia'));
        $this->assertSame(1, LeadScoreConfiguracao::count());
    }

    public function test_soma_dos_pesos_diferente_de_100_e_recusada(): void
    {
        $this->actingAs($this->admin());

        $pagina = Livewire::test(ConfiguracaoLeadScore::class);
        $valores = (new \ReflectionMethod(ConfiguracaoLeadScore::class, 'valoresAtuais'))->invoke(new ConfiguracaoLeadScore);
        $valores['pesos']['distancia'] = 30;

        $pagina->fillForm($valores, 'content')->call('salvar')->assertNotified('Configuração inválida');

        $this->assertSame(0, LeadScoreConfiguracao::count());
    }

    public function test_restaurar_padrao_remove_a_personalizacao(): void
    {
        $this->actingAs($this->admin());
        LeadScoreConfiguracao::create(['valores' => ['faixas_cor' => ['quente' => 90, 'morno' => 50]]]);
        LeadScoreConfiguracao::limparCache();

        Livewire::test(ConfiguracaoLeadScore::class)->call('restaurarPadrao');

        $this->assertSame(0, LeadScoreConfiguracao::count());
        $this->assertSame(70, config('lead_score.faixas_cor.quente'));
    }
}
