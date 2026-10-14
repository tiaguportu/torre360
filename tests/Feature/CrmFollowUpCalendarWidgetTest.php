<?php

namespace Tests\Feature;

use App\Filament\Widgets\CrmFollowUpCalendarWidget;
use App\Models\Interessado;
use App\Models\User;
use App\Models\VisitaInteressado;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CrmFollowUpCalendarWidgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['super_admin', 'admin', 'secretaria'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_consultor_ve_proprio_lead_com_contato_agendado(): void
    {
        $consultor = User::factory()->create(['activated_at' => now()]);
        $consultor->assignRole('secretaria');

        $lead = Interessado::factory()->emAtendimento()->create(['usuario_id' => $consultor->id]);

        $events = Livewire::actingAs($consultor)
            ->test(CrmFollowUpCalendarWidget::class)
            ->instance()
            ->getEvents();

        $ids = collect($events)->pluck('id');

        $this->assertTrue($ids->contains((string) $lead->id));
    }

    public function test_consultor_nao_ve_lead_de_outro_consultor(): void
    {
        $consultor = User::factory()->create(['activated_at' => now()]);
        $consultor->assignRole('secretaria');

        $outroConsultor = User::factory()->create(['activated_at' => now()]);
        $outroConsultor->assignRole('secretaria');

        $leadAlheio = Interessado::factory()->emAtendimento()->create(['usuario_id' => $outroConsultor->id]);

        $events = Livewire::actingAs($consultor)
            ->test(CrmFollowUpCalendarWidget::class)
            ->instance()
            ->getEvents();

        $ids = collect($events)->pluck('id');

        $this->assertFalse($ids->contains((string) $leadAlheio->id));
    }

    public function test_super_admin_ve_leads_de_qualquer_consultor(): void
    {
        $superAdmin = User::factory()->create(['activated_at' => now()]);
        $superAdmin->assignRole('super_admin');

        $consultor = User::factory()->create(['activated_at' => now()]);
        $consultor->assignRole('secretaria');

        $lead = Interessado::factory()->emAtendimento()->create(['usuario_id' => $consultor->id]);

        $events = Livewire::actingAs($superAdmin)
            ->test(CrmFollowUpCalendarWidget::class)
            ->instance()
            ->getEvents();

        $ids = collect($events)->pluck('id');

        $this->assertTrue($ids->contains((string) $lead->id));
    }

    public function test_pagina_de_interessados_carrega_com_o_widget_anexado(): void
    {
        $superAdmin = User::factory()->create(['activated_at' => now()]);
        $superAdmin->assignRole('super_admin');

        $this->actingAs($superAdmin)
            ->get('/admin/interessados')
            ->assertOk();
    }

    public function test_lead_convertido_nao_aparece_no_calendario(): void
    {
        $consultor = User::factory()->create(['activated_at' => now()]);
        $consultor->assignRole('secretaria');

        $leadConvertido = Interessado::factory()
            ->matriculado()
            ->create([
                'usuario_id' => $consultor->id,
                'data_proximo_contato' => now()->addDays(2),
            ]);

        $events = Livewire::actingAs($consultor)
            ->test(CrmFollowUpCalendarWidget::class)
            ->instance()
            ->getEvents();

        $ids = collect($events)->pluck('id');

        $this->assertFalse($ids->contains((string) $leadConvertido->id));
    }

    public function test_consultor_ve_follow_ups_e_visitas_proprios_e_sem_dono_mas_nao_de_outro(): void
    {
        $consultorA = User::factory()->create(['activated_at' => now()]);
        $consultorB = User::factory()->create(['activated_at' => now()]);

        // Follow-ups
        $leadProprio = Interessado::factory()->emAtendimento()->create([
            'usuario_id' => $consultorA->id,
            'data_proximo_contato' => now()->addDays(2),
        ]);
        $leadSemDono = Interessado::factory()->emAtendimento()->create([
            'usuario_id' => null,
            'data_proximo_contato' => now()->addDays(3),
        ]);
        $leadAlheio = Interessado::factory()->emAtendimento()->create([
            'usuario_id' => $consultorB->id,
            'data_proximo_contato' => now()->addDays(4),
        ]);

        // Visitas
        $visitaPropria = VisitaInteressado::factory()->create([
            'interessado_id' => $leadProprio->id,
            'data_hora' => now()->addDays(2),
        ]);
        $visitaSemDono = VisitaInteressado::factory()->create([
            'interessado_id' => $leadSemDono->id,
            'data_hora' => now()->addDays(3),
        ]);
        $visitaAlheia = VisitaInteressado::factory()->create([
            'interessado_id' => $leadAlheio->id,
            'data_hora' => now()->addDays(4),
        ]);

        $events = Livewire::actingAs($consultorA)
            ->test(CrmFollowUpCalendarWidget::class)
            ->instance()
            ->getEvents();

        $ids = collect($events)->pluck('id');

        $this->assertTrue($ids->contains((string) $leadProprio->id), 'Deveria conter lead próprio');
        $this->assertTrue($ids->contains((string) $leadSemDono->id), 'Deveria conter lead sem dono');
        $this->assertFalse($ids->contains((string) $leadAlheio->id), 'Não deveria conter lead de outro consultor');

        $this->assertTrue($ids->contains('visita-'.$visitaPropria->id), 'Deveria conter visita própria');
        $this->assertTrue($ids->contains('visita-'.$visitaSemDono->id), 'Deveria conter visita de lead sem dono');
        $this->assertFalse($ids->contains('visita-'.$visitaAlheia->id), 'Não deveria conter visita de lead alheio');
    }

    public function test_fixed_consultor_id_e_locked_e_nao_permite_bypassar_escopo(): void
    {
        $consultorA = User::factory()->create(['activated_at' => now()]);
        $consultorB = User::factory()->create(['activated_at' => now()]);

        $leadAlheio = Interessado::factory()->emAtendimento()->create([
            'usuario_id' => $consultorB->id,
            'data_proximo_contato' => now()->addDays(2),
        ]);

        // 1. Confirma que a propriedade tem #[Locked]
        $prop = new \ReflectionProperty(CrmFollowUpCalendarWidget::class, 'fixedConsultorId');
        $this->assertNotEmpty($prop->getAttributes(\Livewire\Attributes\Locked::class), 'Propriedade fixedConsultorId deve estar anotada com #[Locked]');

        // 2. Tentar setar via Livewire é recusado (status de erro no componente)
        $test = Livewire::actingAs($consultorA)
            ->test(CrmFollowUpCalendarWidget::class);

        $test->set('fixedConsultorId', $consultorB->id);
        $test->assertForbidden();

        // 3. Mesmo se passado na inicialização, o escopo do usuário logado nunca é bypassado
        $events = Livewire::actingAs($consultorA)
            ->test(CrmFollowUpCalendarWidget::class, ['fixedConsultorId' => $consultorB->id])
            ->instance()
            ->getEvents();

        $ids = collect($events)->pluck('id');
        $this->assertFalse($ids->contains((string) $leadAlheio->id), 'fixedConsultorId nunca deve dar bypass no escopo');
    }

    public function test_select_consultor_e_oculto_para_consultor_comum_e_visivel_para_view_all(): void
    {
        $consultor = User::factory()->create(['activated_at' => now()]);
        $widgetConsultor = Livewire::actingAs($consultor)
            ->test(CrmFollowUpCalendarWidget::class)
            ->instance();

        $selectConsultor = $widgetConsultor->form->getComponentByStatePath('consultores', withHidden: true);
        $this->assertNotNull($selectConsultor);
        $this->assertTrue($selectConsultor->isHidden(), 'Select de consultor deve ficar oculto para consultor comum');

        $gestor = User::factory()->create(['activated_at' => now()]);
        $gestor->givePermissionTo(
            Permission::firstOrCreate(['name' => 'ViewAll:Interessado', 'guard_name' => 'web'])
        );
        $widgetGestor = Livewire::actingAs($gestor)
            ->test(CrmFollowUpCalendarWidget::class)
            ->instance();

        $selectGestor = $widgetGestor->form->getComponentByStatePath('consultores', withHidden: true);
        $this->assertNotNull($selectGestor);
        $this->assertFalse($selectGestor->isHidden(), 'Select de consultor deve ser visível para quem tem ViewAll:Interessado');
    }

    public function test_roles_seeder_concede_view_all_interessado_para_papel_admin(): void
    {
        $this->seed(RolesSeeder::class);

        $adminRole = Role::findByName('admin', 'web');
        $this->assertTrue(
            $adminRole->hasPermissionTo('ViewAll:Interessado'),
            'Papel admin deve receber ViewAll:Interessado após executar RolesSeeder'
        );
    }
}
