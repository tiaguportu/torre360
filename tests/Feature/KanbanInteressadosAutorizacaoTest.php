<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Interessados\Pages\KanbanInteressados;
use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

/**
 * O Kanban expõe métodos Livewire públicos: a autorização precisa ser checada no servidor
 * (não só escondendo o arraste) e os ids do modal de perda não podem ser adulterados pelo cliente.
 */
class KanbanInteressadosAutorizacaoTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    private StatusInteressado $statusNovo;

    private StatusInteressado $statusAtendimento;

    private StatusInteressado $statusMatriculado;

    private StatusInteressado $statusPerdido;

    private OrigemInteressado $origem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->statusNovo = StatusInteressado::create(['nome' => 'Novo', 'cor' => 'info', 'ordem' => 1, 'is_final' => false, 'is_ganho' => false]);
        $this->statusAtendimento = StatusInteressado::create(['nome' => 'Em Atendimento', 'cor' => 'warning', 'ordem' => 2, 'is_final' => false, 'is_ganho' => false]);
        $this->statusMatriculado = StatusInteressado::create(['nome' => 'Matriculado', 'cor' => 'success', 'ordem' => 3, 'is_final' => true, 'is_ganho' => true]);
        $this->statusPerdido = StatusInteressado::create(['nome' => 'Perdido', 'cor' => 'danger', 'ordem' => 4, 'is_final' => true, 'is_ganho' => false]);
        $this->origem = OrigemInteressado::create(['nome' => 'Site']);
        TipoContatoInteressado::firstOrCreate(['nome' => 'Presencial']);
    }

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        return $admin;
    }

    /**
     * Usuário que enxerga o funil (ViewAny/View) mas não tem permissão de editar leads.
     */
    private function somenteLeitura(): User
    {
        $usuario = User::factory()->create();
        $usuario->givePermissionTo(
            Permission::firstOrCreate(['name' => 'ViewAny:Interessado', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'View:Interessado', 'guard_name' => 'web']),
        );

        return $usuario;
    }

    private function criarLead(): Interessado
    {
        return Interessado::create([
            'pessoa_id' => Pessoa::factory()->create()->id,
            'status_interessado_id' => $this->statusNovo->id,
            'origem_interessado_id' => $this->origem->id,
        ]);
    }

    public function test_usuario_sem_permissao_de_edicao_nao_move_lead(): void
    {
        $lead = $this->criarLead();

        Livewire::actingAs($this->somenteLeitura())
            ->test(KanbanInteressados::class)
            ->call('updateRecordStatus', $lead->id, $this->statusAtendimento->id)
            ->assertNotified('Sem permissão');

        $this->assertSame($this->statusNovo->id, $lead->fresh()->status_interessado_id);
    }

    public function test_usuario_sem_permissao_de_edicao_nao_abre_o_modal_nem_marca_perda(): void
    {
        $lead = $this->criarLead();

        Livewire::actingAs($this->somenteLeitura())
            ->test(KanbanInteressados::class)
            ->call('updateRecordStatus', $lead->id, $this->statusPerdido->id)
            ->assertSet('modalPerdaAberto', false)
            ->assertSet('leadPerdaId', null);

        $this->assertSame($this->statusNovo->id, $lead->fresh()->status_interessado_id);
        $this->assertNull($lead->fresh()->motivo_perda);
    }

    public function test_ids_do_modal_de_perda_nao_podem_ser_alterados_pelo_cliente(): void
    {
        $lead = $this->criarLead();
        $outroLead = $this->criarLead();

        $componente = Livewire::actingAs($this->admin())
            ->test(KanbanInteressados::class)
            ->call('updateRecordStatus', $lead->id, $this->statusPerdido->id)
            ->assertSet('leadPerdaId', $lead->id);

        try {
            $componente->set('leadPerdaId', $outroLead->id);
            $this->fail('leadPerdaId deveria ser bloqueado contra alteração pelo cliente.');
        } catch (CannotUpdateLockedPropertyException) {
            $this->assertTrue(true);
        }

        try {
            $componente->set('statusPerdaId', $this->statusMatriculado->id);
            $this->fail('statusPerdaId deveria ser bloqueado contra alteração pelo cliente.');
        } catch (CannotUpdateLockedPropertyException) {
            $this->assertTrue(true);
        }

        $componente->set('motivoPerda', 'Preço')->call('confirmarPerda');

        $this->assertSame($this->statusPerdido->id, $lead->fresh()->status_interessado_id);
        $this->assertSame($this->statusNovo->id, $outroLead->fresh()->status_interessado_id);
    }

    public function test_confirmar_perda_exige_motivo_da_lista_padronizada(): void
    {
        $lead = $this->criarLead();

        Livewire::actingAs($this->admin())
            ->test(KanbanInteressados::class)
            ->call('updateRecordStatus', $lead->id, $this->statusPerdido->id)
            ->set('motivoPerda', 'Motivo inventado pelo cliente')
            ->call('confirmarPerda')
            ->assertHasErrors(['motivoPerda' => 'in']);

        $this->assertSame($this->statusNovo->id, $lead->fresh()->status_interessado_id);
    }

    public function test_permissao_de_edicao_revogada_com_modal_aberto_impede_a_confirmacao(): void
    {
        $lead = $this->criarLead();
        $usuario = $this->somenteLeitura();
        $usuario->givePermissionTo(Permission::firstOrCreate(['name' => 'Update:Interessado', 'guard_name' => 'web']));

        $componente = Livewire::actingAs($usuario)
            ->test(KanbanInteressados::class)
            ->call('updateRecordStatus', $lead->id, $this->statusPerdido->id)
            ->assertSet('modalPerdaAberto', true);

        // Continua enxergando o funil, mas perde a permissão de editar enquanto o modal está aberto.
        $usuario->revokePermissionTo('Update:Interessado');

        $componente
            ->set('motivoPerda', 'Preço')
            ->call('confirmarPerda')
            ->assertNotified('Sem permissão')
            ->assertSet('modalPerdaAberto', false);

        $this->assertSame($this->statusNovo->id, $lead->fresh()->status_interessado_id);
        $this->assertNull($lead->fresh()->motivo_perda);
    }

    public function test_cards_so_ficam_arrastaveis_para_quem_pode_editar(): void
    {
        $this->criarLead();

        Livewire::actingAs($this->admin())
            ->test(KanbanInteressados::class)
            ->assertSeeHtml('draggable="true"');

        Livewire::actingAs($this->somenteLeitura())
            ->test(KanbanInteressados::class)
            ->assertDontSeeHtml('draggable="true"');
    }
}
