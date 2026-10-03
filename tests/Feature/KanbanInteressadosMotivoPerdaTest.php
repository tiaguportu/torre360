<?php

namespace Tests\Feature;

use App\Filament\Resources\Interessados\Pages\KanbanInteressados;
use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

class KanbanInteressadosMotivoPerdaTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    private User $admin;

    private StatusInteressado $statusNovo;

    private StatusInteressado $statusEmAtendimento;

    private StatusInteressado $statusPerdido;

    private StatusInteressado $statusMatriculado;

    private OrigemInteressado $origem;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('super_admin');

        $this->statusNovo = StatusInteressado::create([
            'nome' => 'Novo',
            'cor' => 'info',
            'ordem' => 1,
            'is_final' => false,
            'is_ganho' => false,
        ]);

        $this->statusEmAtendimento = StatusInteressado::create([
            'nome' => 'Em Atendimento',
            'cor' => 'warning',
            'ordem' => 2,
            'is_final' => false,
            'is_ganho' => false,
        ]);

        $this->statusMatriculado = StatusInteressado::create([
            'nome' => 'Matriculado',
            'cor' => 'success',
            'ordem' => 3,
            'is_final' => true,
            'is_ganho' => true,
        ]);

        $this->statusPerdido = StatusInteressado::create([
            'nome' => 'Perdido',
            'cor' => 'danger',
            'ordem' => 4,
            'is_final' => true,
            'is_ganho' => false,
        ]);

        $this->origem = OrigemInteressado::create(['nome' => 'Site']);

        TipoContatoInteressado::firstOrCreate(['nome' => 'Presencial']);
    }

    private function criarLead(array $atributos = []): Interessado
    {
        $pessoa = Pessoa::factory()->create();

        return Interessado::create(array_merge([
            'pessoa_id' => $pessoa->id,
            'status_interessado_id' => $this->statusNovo->id,
            'origem_interessado_id' => $this->origem->id,
            'temperatura' => 'morno',
            'valor_estimado' => 1500.00,
        ], $atributos));
    }

    public function test_kanban_move_lead_para_status_ativo_diretamente(): void
    {
        $lead = $this->criarLead();

        Livewire::actingAs($this->admin)
            ->test(KanbanInteressados::class)
            ->call('updateRecordStatus', $lead->id, $this->statusEmAtendimento->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('interessado', [
            'id' => $lead->id,
            'status_interessado_id' => $this->statusEmAtendimento->id,
            'motivo_perda' => null,
        ]);
    }

    public function test_kanban_intercepta_drop_para_status_perda_e_abre_modal(): void
    {
        $lead = $this->criarLead();

        Livewire::actingAs($this->admin)
            ->test(KanbanInteressados::class)
            ->call('updateRecordStatus', $lead->id, $this->statusPerdido->id)
            ->assertSet('modalPerdaAberto', true)
            ->assertSet('leadPerdaId', $lead->id)
            ->assertSet('statusPerdaId', $this->statusPerdido->id)
            ->assertDispatched('open-modal', id: 'modal-motivo-perda');

        // Garante que o status NÃO foi atualizado ainda
        $this->assertDatabaseHas('interessado', [
            'id' => $lead->id,
            'status_interessado_id' => $this->statusNovo->id,
            'motivo_perda' => null,
        ]);
    }

    public function test_kanban_falha_ao_confirmar_perda_sem_motivo(): void
    {
        $lead = $this->criarLead();

        Livewire::actingAs($this->admin)
            ->test(KanbanInteressados::class)
            ->call('updateRecordStatus', $lead->id, $this->statusPerdido->id)
            ->set('motivoPerda', '')
            ->call('confirmarPerda')
            ->assertHasErrors(['motivoPerda' => 'required']);

        // Lead permanece inalterado
        $this->assertDatabaseHas('interessado', [
            'id' => $lead->id,
            'status_interessado_id' => $this->statusNovo->id,
            'motivo_perda' => null,
        ]);
    }

    public function test_kanban_confirma_perda_com_sucesso_e_registra_historico(): void
    {
        $lead = $this->criarLead();

        Livewire::actingAs($this->admin)
            ->test(KanbanInteressados::class)
            ->call('updateRecordStatus', $lead->id, $this->statusPerdido->id)
            ->set('motivoPerda', 'Preço')
            ->set('observacoesPerda', 'Valor da mensalidade acima do orçamento planejado.')
            ->call('confirmarPerda')
            ->assertHasNoErrors()
            ->assertSet('modalPerdaAberto', false)
            ->assertDispatched('close-modal', id: 'modal-motivo-perda');

        $this->assertDatabaseHas('interessado', [
            'id' => $lead->id,
            'status_interessado_id' => $this->statusPerdido->id,
            'motivo_perda' => 'Preço',
        ]);

        // Verifica se registrou histórico de contato com o relato completo
        $this->assertDatabaseHas('historico_contato', [
            'interessado_id' => $lead->id,
            'usuario_id' => $this->admin->id,
            'resultado' => 'sem_interesse',
        ]);

        $historico = HistoricoContato::where('interessado_id', $lead->id)->latest()->first();
        $this->assertNotNull($historico);
        $this->assertStringContainsString('Preço', $historico->relato);
        $this->assertStringContainsString('Valor da mensalidade acima do orçamento', $historico->relato);
    }

    public function test_kanban_confirma_perda_por_concorrencia_com_nome_da_escola(): void
    {
        $lead = $this->criarLead();

        Livewire::actingAs($this->admin)
            ->test(KanbanInteressados::class)
            ->call('updateRecordStatus', $lead->id, $this->statusPerdido->id)
            ->set('motivoPerda', 'Concorrência')
            ->set('concorrentePerda', 'Colégio São Francisco')
            ->call('confirmarPerda')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('interessado', [
            'id' => $lead->id,
            'status_interessado_id' => $this->statusPerdido->id,
            'motivo_perda' => 'Concorrência: Colégio São Francisco',
        ]);
    }

    public function test_kanban_fechar_modal_cancela_operacao_e_mantem_status_original(): void
    {
        $lead = $this->criarLead();

        Livewire::actingAs($this->admin)
            ->test(KanbanInteressados::class)
            ->call('updateRecordStatus', $lead->id, $this->statusPerdido->id)
            ->assertSet('modalPerdaAberto', true)
            ->call('fecharModalPerda')
            ->assertSet('modalPerdaAberto', false)
            ->assertDispatched('close-modal', id: 'modal-motivo-perda');

        $this->assertDatabaseHas('interessado', [
            'id' => $lead->id,
            'status_interessado_id' => $this->statusNovo->id,
            'motivo_perda' => null,
        ]);
    }

    public function test_kanban_reativar_lead_perdido_limpa_motivo_perda_e_registra_historico(): void
    {
        $lead = $this->criarLead([
            'status_interessado_id' => $this->statusPerdido->id,
            'motivo_perda' => 'Preço',
        ]);

        Livewire::actingAs($this->admin)
            ->test(KanbanInteressados::class)
            ->call('updateRecordStatus', $lead->id, $this->statusEmAtendimento->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('interessado', [
            'id' => $lead->id,
            'status_interessado_id' => $this->statusEmAtendimento->id,
            'motivo_perda' => null,
        ]);

        // Verifica se registrou reativação no histórico de contatos
        $historico = HistoricoContato::where('interessado_id', $lead->id)->latest()->first();
        $this->assertNotNull($historico);
        $this->assertStringContainsString('Lead reativado no Funil de Vendas', $historico->relato);
        $this->assertEquals('retornar', $historico->resultado);
    }
}
