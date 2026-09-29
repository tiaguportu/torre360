<?php

namespace Tests\Feature;

use App\Filament\Resources\LandingLeads\LandingLeadResource;
use App\Filament\Resources\LandingLeads\Pages\ListLandingLeads;
use App\Models\LandingLead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LandingLeadResourceTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = User::factory()->create(['activated_at' => now()]);
        $user->assignRole($role);

        return $user;
    }

    private function lead(array $atributos = []): LandingLead
    {
        return LandingLead::create(array_merge([
            'nome' => 'Escola Exemplo',
            'email' => 'contato@escola.com',
            'whatsapp' => '11988887777',
            'mensagem' => 'Queremos uma demonstração.',
        ], $atributos));
    }

    public function test_admin_lista_leads_da_landing(): void
    {
        $lead = $this->lead();

        Livewire::actingAs($this->usuario('super_admin'))
            ->test(ListLandingLeads::class)
            ->assertCanSeeTableRecords([$lead])
            ->assertSee('Escola Exemplo');
    }

    public function test_lead_recem_captado_entra_como_novo(): void
    {
        $this->assertSame(LandingLead::STATUS_NOVO, $this->lead()->fresh()->status);
    }

    public function test_marcar_em_contato_e_descartar_e_reabrir(): void
    {
        $lead = $this->lead();

        $tela = Livewire::actingAs($this->usuario('super_admin'))->test(ListLandingLeads::class);

        $tela->callTableAction('emContato', $lead);
        $this->assertSame(LandingLead::STATUS_EM_CONTATO, $lead->fresh()->status);

        $tela->callTableAction('descartar', $lead);
        $this->assertSame(LandingLead::STATUS_DESCARTADO, $lead->fresh()->status);

        $tela->callTableAction('reabrir', $lead);
        $this->assertSame(LandingLead::STATUS_NOVO, $lead->fresh()->status);
    }

    public function test_acoes_ficam_ocultas_conforme_o_status(): void
    {
        $descartado = $this->lead(['status' => LandingLead::STATUS_DESCARTADO]);
        $novo = $this->lead(['email' => 'outra@escola.com']);

        Livewire::actingAs($this->usuario('super_admin'))
            ->test(ListLandingLeads::class)
            ->assertTableActionHidden('descartar', $descartado)
            ->assertTableActionVisible('reabrir', $descartado)
            ->assertTableActionVisible('emContato', $novo)
            ->assertTableActionHidden('reabrir', $novo);
    }

    public function test_filtro_de_status(): void
    {
        $novo = $this->lead();
        $descartado = $this->lead(['status' => LandingLead::STATUS_DESCARTADO, 'email' => 'x@escola.com']);

        Livewire::actingAs($this->usuario('super_admin'))
            ->test(ListLandingLeads::class)
            ->filterTable('status', LandingLead::STATUS_DESCARTADO)
            ->assertCanSeeTableRecords([$descartado])
            ->assertCanNotSeeTableRecords([$novo]);
    }

    public function test_secretaria_sem_permissao_nao_acessa_a_tela(): void
    {
        $this->lead();

        $this->actingAs($this->usuario('secretaria'))
            ->get(LandingLeadResource::getUrl('index'))
            ->assertForbidden();
    }

    public function test_leads_da_landing_nao_sao_criados_pelo_painel(): void
    {
        $this->actingAs($this->usuario('super_admin'));

        $this->assertFalse(LandingLeadResource::canCreate());
    }

    public function test_formulario_publico_da_landing_continua_gravando_lead_novo(): void
    {
        config(['services.recaptcha.site_key' => null, 'services.recaptcha.secret' => null]);

        $this->post(route('solicitar-acesso'), [
            'nome' => 'Diretora Ana',
            'email' => 'ana@escola.com',
            'whatsapp' => '21999990000',
            'mensagem' => 'Quero conhecer.',
        ])->assertRedirect();

        $this->assertDatabaseHas('landing_leads', ['email' => 'ana@escola.com', 'status' => 'novo']);
    }
}
