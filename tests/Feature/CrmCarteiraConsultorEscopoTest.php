<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Interessados\Pages\EditInteressado;
use App\Filament\Resources\Interessados\Pages\KanbanInteressados;
use App\Filament\Resources\Interessados\Pages\ListInteressados;
use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

/**
 * Testes para o Lote D7: Escopo de visibilidade da carteira do consultor,
 * acesso total por super_admin/ViewAll:Interessado e permissão de assumir leads não atribuídos.
 */
class CrmCarteiraConsultorEscopoTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    private StatusInteressado $statusNovo;

    private StatusInteressado $statusAtendimento;

    private OrigemInteressado $origem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->statusNovo = StatusInteressado::create([
            'nome' => 'Novo',
            'cor' => 'info',
            'ordem' => 1,
            'is_final' => false,
            'is_ganho' => false,
        ]);

        $this->statusAtendimento = StatusInteressado::create([
            'nome' => 'Em Atendimento',
            'cor' => 'warning',
            'ordem' => 2,
            'is_final' => false,
            'is_ganho' => false,
        ]);

        $this->origem = OrigemInteressado::create(['nome' => 'Site']);
        TipoContatoInteressado::firstOrCreate(['nome' => TipoContatoInteressado::FUNIL]);
    }

    private function criarConsultor(string $nome = 'Consultor'): User
    {
        $usuario = User::factory()->create(['name' => $nome]);
        $usuario->givePermissionTo(
            Permission::firstOrCreate(['name' => 'ViewAny:Interessado', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'View:Interessado', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'Update:Interessado', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'Create:Interessado', 'guard_name' => 'web']),
        );

        return $usuario;
    }

    private function criarSuperAdmin(): User
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['name' => 'Super Admin']);
        $admin->assignRole('super_admin');

        return $admin;
    }

    private function criarConsultorComViewAll(string $nome = 'Gestor Comercial'): User
    {
        $usuario = $this->criarConsultor($nome);
        $usuario->givePermissionTo(
            Permission::firstOrCreate(['name' => 'ViewAll:Interessado', 'guard_name' => 'web']),
        );

        return $usuario;
    }

    private function criarLead(?User $consultor = null, string $nomeLead = 'Aluno Lead'): Interessado
    {
        $pessoa = Pessoa::factory()->create(['nome' => $nomeLead]);

        return Interessado::create([
            'pessoa_id' => $pessoa->id,
            'status_interessado_id' => $this->statusNovo->id,
            'origem_interessado_id' => $this->origem->id,
            'usuario_id' => $consultor?->id,
        ]);
    }

    public function test_consultor_comum_visualiza_apenas_sua_carteira_e_leads_nao_atribuidos(): void
    {
        $consultorA = $this->criarConsultor('Consultor A');
        $consultorB = $this->criarConsultor('Consultor B');

        $leadDeA = $this->criarLead($consultorA, 'Lead de A');
        $leadDeB = $this->criarLead($consultorB, 'Lead de B');
        $leadNaoAtribuido = $this->criarLead(null, 'Lead Sem Consultor');

        $leadsVisiveisParaA = Interessado::visivelPara($consultorA)->pluck('id');

        $this->assertTrue($leadsVisiveisParaA->contains($leadDeA->id));
        $this->assertTrue($leadsVisiveisParaA->contains($leadNaoAtribuido->id));
        $this->assertFalse($leadsVisiveisParaA->contains($leadDeB->id));
    }

    public function test_super_admin_e_usuario_com_permissao_view_all_visualizam_todos_os_leads(): void
    {
        $consultorA = $this->criarConsultor('Consultor A');
        $consultorB = $this->criarConsultor('Consultor B');
        $superAdmin = $this->criarSuperAdmin();
        $gestor = $this->criarConsultorComViewAll();

        $leadDeA = $this->criarLead($consultorA);
        $leadDeB = $this->criarLead($consultorB);
        $leadSemConsultor = $this->criarLead(null);

        $leadsSuperAdmin = Interessado::visivelPara($superAdmin)->pluck('id');
        $this->assertTrue($leadsSuperAdmin->contains($leadDeA->id));
        $this->assertTrue($leadsSuperAdmin->contains($leadDeB->id));
        $this->assertTrue($leadsSuperAdmin->contains($leadSemConsultor->id));

        $leadsGestor = Interessado::visivelPara($gestor)->pluck('id');
        $this->assertTrue($leadsGestor->contains($leadDeA->id));
        $this->assertTrue($leadsGestor->contains($leadDeB->id));
        $this->assertTrue($leadsGestor->contains($leadSemConsultor->id));
    }

    public function test_consultor_comum_nao_pode_visualizar_nem_editar_lead_de_outro_consultor_na_policy(): void
    {
        $consultorA = $this->criarConsultor('Consultor A');
        $consultorB = $this->criarConsultor('Consultor B');

        $leadDeB = $this->criarLead($consultorB);
        $leadDeA = $this->criarLead($consultorA);
        $leadSemConsultor = $this->criarLead(null);

        // Consultor A tentando acessar lead de B
        $this->assertFalse($consultorA->can('view', $leadDeB));
        $this->assertFalse($consultorA->can('update', $leadDeB));

        // Consultor A acessando seu próprio lead
        $this->assertTrue($consultorA->can('view', $leadDeA));
        $this->assertTrue($consultorA->can('update', $leadDeA));

        // Consultor A acessando lead não atribuído
        $this->assertTrue($consultorA->can('view', $leadSemConsultor));
        $this->assertTrue($consultorA->can('update', $leadSemConsultor));
    }

    public function test_listagem_respeita_escopo_do_consultor_na_tabela(): void
    {
        $consultorA = $this->criarConsultor('Consultor A');
        $consultorB = $this->criarConsultor('Consultor B');

        $leadDeA = $this->criarLead($consultorA, 'Lead Alfa');
        $leadDeB = $this->criarLead($consultorB, 'Lead Beta');
        $leadSemConsultor = $this->criarLead(null, 'Lead Gama');

        Livewire::actingAs($consultorA)
            ->test(ListInteressados::class)
            ->assertCanSeeTableRecords([$leadDeA, $leadSemConsultor])
            ->assertCanNotSeeTableRecords([$leadDeB]);
    }

    public function test_kanban_respeita_escopo_da_carteira_do_consultor(): void
    {
        $consultorA = $this->criarConsultor('Consultor A');
        $consultorB = $this->criarConsultor('Consultor B');

        $leadDeA = $this->criarLead($consultorA, 'Lead Kanban A');
        $leadDeB = $this->criarLead($consultorB, 'Lead Kanban B');
        $leadSemConsultor = $this->criarLead(null, 'Lead Kanban Sem Dono');

        $componente = Livewire::actingAs($consultorA)
            ->test(KanbanInteressados::class);

        $colunas = $componente->get('colunas');
        $todosIds = collect($colunas)->flatMap(fn (array $col) => collect($col['leads'])->pluck('id'));

        $this->assertTrue($todosIds->contains($leadDeA->id));
        $this->assertTrue($todosIds->contains($leadSemConsultor->id));
        $this->assertFalse($todosIds->contains($leadDeB->id));
    }

    public function test_consultor_comum_nao_move_card_de_outro_consultor_no_kanban(): void
    {
        $consultorA = $this->criarConsultor('Consultor A');
        $consultorB = $this->criarConsultor('Consultor B');

        $leadDeB = $this->criarLead($consultorB);

        Livewire::actingAs($consultorA)
            ->test(KanbanInteressados::class)
            ->call('updateRecordStatus', $leadDeB->id, $this->statusAtendimento->id)
            ->assertNotified('Sem permissão');

        $this->assertSame($this->statusNovo->id, $leadDeB->fresh()->status_interessado_id);
    }

    public function test_consultor_pode_assumir_lead_nao_atribuido_pela_tabela(): void
    {
        $consultor = $this->criarConsultor('Consultor Atendente');
        $leadSemConsultor = $this->criarLead(null, 'Família Interessada');

        Livewire::actingAs($consultor)
            ->test(ListInteressados::class)
            ->callTableAction('assumirLead', $leadSemConsultor)
            ->assertNotified('Lead atribuído à sua carteira com sucesso!');

        $leadAtualizado = $leadSemConsultor->fresh();
        $this->assertSame($consultor->id, $leadAtualizado->usuario_id);

        $this->assertDatabaseHas('historico_contato', [
            'interessado_id' => $leadSemConsultor->id,
            'usuario_id' => $consultor->id,
        ]);
    }

    public function test_consultor_pode_assumir_lead_nao_atribuido_pelo_kanban(): void
    {
        $consultor = $this->criarConsultor('Consultor Kanban');
        $leadSemConsultor = $this->criarLead(null);

        Livewire::actingAs($consultor)
            ->test(KanbanInteressados::class)
            ->call('assumirLead', $leadSemConsultor->id)
            ->assertNotified('Lead atribuído à sua carteira com sucesso!');

        $this->assertSame($consultor->id, $leadSemConsultor->fresh()->usuario_id);
    }

    public function test_consultor_pode_assumir_lead_nao_atribuido_pela_pagina_de_edicao(): void
    {
        $consultor = $this->criarConsultor('Consultor Edicao');
        $leadSemConsultor = $this->criarLead(null);

        Livewire::actingAs($consultor)
            ->test(EditInteressado::class, ['record' => $leadSemConsultor->id])
            ->callAction('assumirLead')
            ->assertNotified('Lead atribuído à sua carteira com sucesso!');

        $this->assertSame($consultor->id, $leadSemConsultor->fresh()->usuario_id);
    }

    public function test_nao_e_permitido_assumir_lead_que_ja_possui_outro_consultor(): void
    {
        $consultorA = $this->criarConsultor('Consultor A');
        $consultorB = $this->criarConsultor('Consultor B');

        $leadDeA = $this->criarLead($consultorA);

        $this->assertFalse($leadDeA->podeSerAssumidoPor($consultorB));

        Livewire::actingAs($consultorB)
            ->test(KanbanInteressados::class)
            ->call('assumirLead', $leadDeA->id);

        // Deve manter o consultor A intacto
        $this->assertSame($consultorA->id, $leadDeA->fresh()->usuario_id);
    }
}
