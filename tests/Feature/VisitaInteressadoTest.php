<?php

namespace Tests\Feature;

use App\Enums\StatusVisitaInteressado;
use App\Filament\Resources\Interessados\Pages\ListInteressados;
use App\Filament\Widgets\CrmFollowUpCalendarWidget;
use App\Models\Interessado;
use App\Models\User;
use App\Models\VisitaInteressado;
use App\Services\VisitaInteressadoService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VisitaInteressadoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['super_admin', 'admin', 'secretaria'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    private function consultor(string $role = 'secretaria'): User
    {
        $user = User::factory()->create(['activated_at' => now()]);
        $user->assignRole($role);

        return $user;
    }

    public function test_agendar_visita_cria_registro_e_define_proximo_contato(): void
    {
        $consultor = $this->consultor();
        $lead = Interessado::factory()->emAtendimento()->create([
            'usuario_id' => $consultor->id,
            'data_proximo_contato' => null,
        ]);

        $dataHora = now()->addDays(3)->setTime(10, 0);

        $visita = VisitaInteressadoService::agendar($lead, $dataHora);

        $this->assertSame(StatusVisitaInteressado::Agendada, $visita->status);
        $this->assertSame($consultor->id, $visita->usuario_id);
        $this->assertTrue($lead->fresh()->data_proximo_contato->eq($dataHora));
    }

    public function test_agendar_visita_nao_adia_retorno_ja_marcado_para_antes(): void
    {
        $lead = Interessado::factory()->emAtendimento()->create([
            'data_proximo_contato' => now()->addDay()->setTime(9, 0),
        ]);

        VisitaInteressadoService::agendar($lead, now()->addDays(5)->setTime(10, 0));

        $this->assertTrue($lead->fresh()->data_proximo_contato->isSameDay(now()->addDay()));
    }

    public function test_agendar_visita_antecipa_o_proximo_contato_quando_visita_e_anterior(): void
    {
        $lead = Interessado::factory()->emAtendimento()->create([
            'data_proximo_contato' => now()->addDays(10),
        ]);

        $dataHora = now()->addDays(2)->setTime(15, 0);
        VisitaInteressadoService::agendar($lead, $dataHora);

        $this->assertTrue($lead->fresh()->data_proximo_contato->eq($dataHora));
    }

    public function test_proxima_visita_ignora_realizadas_e_passadas(): void
    {
        $lead = Interessado::factory()->emAtendimento()->create();

        VisitaInteressado::factory()->realizada()->create(['interessado_id' => $lead->id]);
        $futura = VisitaInteressado::factory()->create(['interessado_id' => $lead->id, 'data_hora' => now()->addDays(4)]);
        VisitaInteressado::factory()->create(['interessado_id' => $lead->id, 'data_hora' => now()->addDays(9)]);

        $this->assertTrue($lead->fresh()->proximaVisita->is($futura));
    }

    public function test_lembrete_notifica_consultor_das_visitas_nas_proximas_24h_uma_unica_vez(): void
    {
        $consultor = $this->consultor();
        $lead = Interessado::factory()->emAtendimento()->create(['usuario_id' => $consultor->id]);

        $proxima = VisitaInteressado::factory()->daquiAHoras(10)->create([
            'interessado_id' => $lead->id,
            'usuario_id' => $consultor->id,
        ]);
        $distante = VisitaInteressado::factory()->daquiAHoras(60)->create(['interessado_id' => $lead->id, 'usuario_id' => $consultor->id]);
        $cancelada = VisitaInteressado::factory()->daquiAHoras(5)->create([
            'interessado_id' => $lead->id,
            'usuario_id' => $consultor->id,
            'status' => StatusVisitaInteressado::Cancelada,
        ]);

        $this->assertSame(1, VisitaInteressadoService::enviarLembretes());
        $this->assertSame(0, VisitaInteressadoService::enviarLembretes());

        $this->assertNotNull($proxima->fresh()->lembrete_enviado_em);
        $this->assertNull($distante->fresh()->lembrete_enviado_em);
        $this->assertNull($cancelada->fresh()->lembrete_enviado_em);
        $this->assertSame(1, $consultor->notifications()->count());
    }

    public function test_lembrete_usa_consultor_do_lead_quando_visita_nao_tem_responsavel(): void
    {
        $consultor = $this->consultor();
        $lead = Interessado::factory()->emAtendimento()->create(['usuario_id' => $consultor->id]);

        VisitaInteressado::factory()->daquiAHoras(3)->create(['interessado_id' => $lead->id, 'usuario_id' => null]);

        $this->assertSame(1, VisitaInteressadoService::enviarLembretes());
        $this->assertSame(1, $consultor->notifications()->count());
    }

    public function test_comando_crm_notificar_pendentes_envia_lembretes_de_visita(): void
    {
        $consultor = $this->consultor();
        $lead = Interessado::factory()->emAtendimento()->create([
            'usuario_id' => $consultor->id,
            'data_proximo_contato' => now()->addDays(5),
        ]);
        VisitaInteressado::factory()->daquiAHoras(6)->create(['interessado_id' => $lead->id, 'usuario_id' => $consultor->id]);

        $this->artisan('crm:notificar-pendentes')
            ->expectsOutputToContain('Lembretes de visita enviados: 1.')
            ->assertSuccessful();

        $this->assertSame(1, $consultor->notifications()->count());
    }

    public function test_calendario_exibe_visitas_agendadas_do_proprio_consultor(): void
    {
        $consultor = $this->consultor();
        $lead = Interessado::factory()->emAtendimento()->create(['usuario_id' => $consultor->id]);
        $visita = VisitaInteressado::factory()->create(['interessado_id' => $lead->id, 'usuario_id' => $consultor->id]);

        $events = Livewire::actingAs($consultor)->test(CrmFollowUpCalendarWidget::class)->instance()->getEvents();

        $evento = collect($events)->firstWhere('id', 'visita-'.$visita->id);

        $this->assertNotNull($evento);
        $this->assertStringStartsWith('Visita: ', $evento['title']);
        $this->assertSame('Visita agendada', $evento['extendedProps']['status']);
    }

    public function test_calendario_nao_exibe_visita_de_outro_consultor_nem_realizada(): void
    {
        $consultor = $this->consultor();
        $outro = $this->consultor();

        $leadAlheio = Interessado::factory()->emAtendimento()->create(['usuario_id' => $outro->id]);
        $visitaAlheia = VisitaInteressado::factory()->create(['interessado_id' => $leadAlheio->id, 'usuario_id' => $outro->id]);

        $leadProprio = Interessado::factory()->emAtendimento()->create(['usuario_id' => $consultor->id]);
        $visitaRealizada = VisitaInteressado::factory()->realizada()->create(['interessado_id' => $leadProprio->id, 'usuario_id' => $consultor->id]);

        $ids = collect(Livewire::actingAs($consultor)->test(CrmFollowUpCalendarWidget::class)->instance()->getEvents())->pluck('id');

        $this->assertFalse($ids->contains('visita-'.$visitaAlheia->id));
        $this->assertFalse($ids->contains('visita-'.$visitaRealizada->id));
    }

    public function test_super_admin_ve_visitas_de_qualquer_consultor_no_calendario(): void
    {
        $admin = $this->consultor('super_admin');
        $consultor = $this->consultor();

        $lead = Interessado::factory()->emAtendimento()->create(['usuario_id' => $consultor->id]);
        $visita = VisitaInteressado::factory()->create(['interessado_id' => $lead->id, 'usuario_id' => $consultor->id]);

        $ids = collect(Livewire::actingAs($admin)->test(CrmFollowUpCalendarWidget::class)->instance()->getEvents())->pluck('id');

        $this->assertTrue($ids->contains('visita-'.$visita->id));
    }

    public function test_acao_agendar_visita_da_tabela_de_interessados(): void
    {
        $admin = $this->consultor('super_admin');
        $lead = Interessado::factory()->emAtendimento()->create(['usuario_id' => $admin->id]);

        $dataHora = now()->addDays(2)->setTime(14, 30)->format('Y-m-d H:i:s');

        Livewire::actingAs($admin)
            ->test(ListInteressados::class)
            ->callAction(TestAction::make('agendarVisita')->table($lead), [
                'data_hora' => $dataHora,
                'observacoes' => 'Conhecer a estrutura',
            ])
            ->assertHasNoActionErrors()
            ->assertNotified();

        $this->assertDatabaseHas('visita_interessado', [
            'interessado_id' => $lead->id,
            'usuario_id' => $admin->id,
            'status' => 'agendada',
            'observacoes' => 'Conhecer a estrutura',
        ]);
    }

    public function test_acao_agendar_visita_fica_oculta_para_lead_em_status_final(): void
    {
        $admin = $this->consultor('super_admin');
        $lead = Interessado::factory()->matriculado()->create();

        Livewire::actingAs($admin)
            ->test(ListInteressados::class)
            ->assertTableActionHidden('agendarVisita', $lead);
    }
}
