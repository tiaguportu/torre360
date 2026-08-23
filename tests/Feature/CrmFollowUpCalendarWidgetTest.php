<?php

namespace Tests\Feature;

use App\Filament\Widgets\CrmFollowUpCalendarWidget;
use App\Models\Interessado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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
}
