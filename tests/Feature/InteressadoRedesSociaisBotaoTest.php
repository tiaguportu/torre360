<?php

namespace Tests\Feature;

use App\Filament\Resources\Interessados\Pages\ListInteressados;
use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InteressadoRedesSociaisBotaoTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::factory()->create(['activated_at' => now()]);
        $admin->assignRole('super_admin');

        return $admin;
    }

    private function lead(?array $redes): Interessado
    {
        $status = StatusInteressado::firstOrCreate(
            ['nome' => 'Em Atendimento'],
            ['cor' => 'warning', 'ordem' => 2, 'is_final' => false, 'is_ganho' => false],
        );

        return Interessado::create([
            'pessoa_id' => Pessoa::factory()->create()->id,
            'origem_interessado_id' => OrigemInteressado::firstOrCreate(['nome' => 'Instagram'])->id,
            'status_interessado_id' => $status->id,
            'redes_sociais' => $redes,
        ]);
    }

    public function test_lead_com_uma_rede_social_tem_botao_que_abre_o_perfil_em_nova_aba(): void
    {
        $lead = $this->lead([['rede' => 'instagram', 'url' => 'https://instagram.com/maria']]);

        Livewire::actingAs($this->admin())
            ->test(ListInteressados::class)
            ->assertTableActionVisible('abrirRedesSociais', $lead)
            ->assertTableActionHasUrl('abrirRedesSociais', 'https://instagram.com/maria', $lead)
            ->assertTableActionShouldOpenUrlInNewTab('abrirRedesSociais', $lead);
    }

    public function test_lead_sem_rede_social_nao_tem_o_botao(): void
    {
        $semRedes = $this->lead(null);
        $listaVazia = $this->lead([]);

        Livewire::actingAs($this->admin())
            ->test(ListInteressados::class)
            ->assertTableActionHidden('abrirRedesSociais', $semRedes)
            ->assertTableActionHidden('abrirRedesSociais', $listaVazia);
    }

    public function test_lead_com_varias_redes_abre_uma_lista_com_um_link_por_perfil(): void
    {
        $lead = $this->lead([
            ['rede' => 'instagram', 'url' => 'https://instagram.com/maria'],
            ['rede' => 'linkedin', 'url' => 'https://linkedin.com/in/maria'],
        ]);

        Livewire::actingAs($this->admin())
            ->test(ListInteressados::class)
            ->assertTableActionVisible('abrirRedesSociais', $lead)
            ->assertTableActionDoesNotHaveUrl('abrirRedesSociais', $lead)
            ->mountTableAction('abrirRedesSociais', $lead)
            ->assertMountedActionModalSee(['Instagram', 'LinkedIn', 'https://instagram.com/maria', 'https://linkedin.com/in/maria'], escape: false);
    }

    public function test_so_links_http_viram_botao_e_rede_desconhecida_vira_outra(): void
    {
        $lead = $this->lead([
            ['rede' => 'instagram', 'url' => 'javascript://%0Aalert(1)'],
            ['rede' => 'facebook', 'url' => 'não é um link'],
            ['rede' => 'myspace', 'url' => 'http://myspace.com/maria'],
            'lixo',
            ['rede' => ['x'], 'url' => ['y']],
        ]);

        $this->assertSame(
            [['rede' => 'outra', 'rotulo' => 'Outra', 'url' => 'http://myspace.com/maria']],
            $lead->redesSociaisLinks()->all(),
        );

        Livewire::actingAs($this->admin())
            ->test(ListInteressados::class)
            ->assertTableActionHasUrl('abrirRedesSociais', 'http://myspace.com/maria', $lead);
    }

    public function test_lead_so_com_links_invalidos_nao_tem_o_botao(): void
    {
        $lead = $this->lead([['rede' => 'instagram', 'url' => 'javascript:alert(1)']]);

        Livewire::actingAs($this->admin())
            ->test(ListInteressados::class)
            ->assertTableActionHidden('abrirRedesSociais', $lead);
    }
}
