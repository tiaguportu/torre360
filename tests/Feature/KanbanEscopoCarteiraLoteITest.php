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
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

/**
 * Testes de blindagem para o Lote I: Garantir que o Kanban respeita
 * rigorosamente o escopo de carteira do consultor em todas as consultas
 * (totais agregados, valores financeiros somados, cards exibidos e paginação).
 */
class KanbanEscopoCarteiraLoteITest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    private StatusInteressado $statusNovo;

    private StatusInteressado $statusAtendimento;

    private StatusInteressado $statusMatriculado;

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

        $this->statusMatriculado = StatusInteressado::create([
            'nome' => 'Matriculado',
            'cor' => 'success',
            'ordem' => 3,
            'is_final' => true,
            'is_ganho' => true,
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

    private function criarUsuarioComViewAll(string $nome = 'Gestor Comercial'): User
    {
        $usuario = $this->criarConsultor($nome);
        $usuario->givePermissionTo(
            Permission::firstOrCreate(['name' => 'ViewAll:Interessado', 'guard_name' => 'web']),
        );

        return $usuario;
    }

    private function criarLead(
        ?User $consultor,
        string $nomeLead,
        float $valorEstimado,
        ?StatusInteressado $status = null,
    ): Interessado {
        $pessoa = Pessoa::factory()->create(['nome' => $nomeLead]);

        return Interessado::create([
            'pessoa_id' => $pessoa->id,
            'status_interessado_id' => ($status ?? $this->statusNovo)->id,
            'origem_interessado_id' => $this->origem->id,
            'usuario_id' => $consultor?->id,
            'valor_estimado' => $valorEstimado,
        ]);
    }

    /**
     * @return array{
     *     consultorA: User,
     *     consultorB: User,
     *     leadA1: Interessado,
     *     leadA2: Interessado,
     *     leadB1: Interessado,
     *     leadB2: Interessado,
     *     leadB3: Interessado,
     *     leadSemDono: Interessado
     * }
     */
    private function criarCenarioBase(): array
    {
        $consultorA = $this->criarConsultor('Consultor A');
        $consultorB = $this->criarConsultor('Consultor B');

        $leadA1 = $this->criarLead($consultorA, 'Lead A1', 100.00);
        $leadA2 = $this->criarLead($consultorA, 'Lead A2', 200.00);

        $leadB1 = $this->criarLead($consultorB, 'Lead B1', 1000.00);
        $leadB2 = $this->criarLead($consultorB, 'Lead B2', 2000.00);
        $leadB3 = $this->criarLead($consultorB, 'Lead B3', 4000.00);

        $leadSemDono = $this->criarLead(null, 'Lead Sem Dono', 50.00);

        return [
            'consultorA' => $consultorA,
            'consultorB' => $consultorB,
            'leadA1' => $leadA1,
            'leadA2' => $leadA2,
            'leadB1' => $leadB1,
            'leadB2' => $leadB2,
            'leadB3' => $leadB3,
            'leadSemDono' => $leadSemDono,
        ];
    }

    public function test_consultor_a_visualiza_apenas_seus_leads_e_sem_dono_com_totais_e_valores_isolados(): void
    {
        $dados = $this->criarCenarioBase();

        $componente = Livewire::actingAs($dados['consultorA'])
            ->test(KanbanInteressados::class);

        $coluna = $componente->instance()->colunas()->firstWhere(fn (array $c) => $c['status']->id === $this->statusNovo->id);

        $this->assertNotNull($coluna);
        $this->assertSame(3, $coluna['total']);
        $this->assertEqualsWithDelta(350.00, $coluna['valor'], 0.01);

        $cardIds = collect($coluna['leads'])->pluck('id');
        $this->assertCount(3, $cardIds);
        $this->assertTrue($cardIds->contains($dados['leadA1']->id));
        $this->assertTrue($cardIds->contains($dados['leadA2']->id));
        $this->assertTrue($cardIds->contains($dados['leadSemDono']->id));
        $this->assertFalse($cardIds->contains($dados['leadB1']->id));
        $this->assertFalse($cardIds->contains($dados['leadB2']->id));
        $this->assertFalse($cardIds->contains($dados['leadB3']->id));
    }

    public function test_consultor_b_visualiza_apenas_seus_leads_e_sem_dono_sem_cards_do_consultor_a(): void
    {
        $dados = $this->criarCenarioBase();

        $componente = Livewire::actingAs($dados['consultorB'])
            ->test(KanbanInteressados::class);

        $coluna = $componente->instance()->colunas()->firstWhere(fn (array $c) => $c['status']->id === $this->statusNovo->id);

        $this->assertNotNull($coluna);
        $this->assertSame(4, $coluna['total']);
        $this->assertEqualsWithDelta(7050.00, $coluna['valor'], 0.01);

        $cardIds = collect($coluna['leads'])->pluck('id');
        $this->assertCount(4, $cardIds);
        $this->assertTrue($cardIds->contains($dados['leadB1']->id));
        $this->assertTrue($cardIds->contains($dados['leadB2']->id));
        $this->assertTrue($cardIds->contains($dados['leadB3']->id));
        $this->assertTrue($cardIds->contains($dados['leadSemDono']->id));
        $this->assertFalse($cardIds->contains($dados['leadA1']->id));
        $this->assertFalse($cardIds->contains($dados['leadA2']->id));
    }

    public function test_super_admin_visualiza_todos_os_leads_totais_e_valores_agregados(): void
    {
        $dados = $this->criarCenarioBase();
        $superAdmin = $this->criarSuperAdmin();

        $componente = Livewire::actingAs($superAdmin)
            ->test(KanbanInteressados::class);

        $coluna = $componente->instance()->colunas()->firstWhere(fn (array $c) => $c['status']->id === $this->statusNovo->id);

        $this->assertNotNull($coluna);
        $this->assertSame(6, $coluna['total']);
        $this->assertEqualsWithDelta(7350.00, $coluna['valor'], 0.01);

        $cardIds = collect($coluna['leads'])->pluck('id');
        $this->assertCount(6, $cardIds);
        $this->assertTrue($cardIds->contains($dados['leadA1']->id));
        $this->assertTrue($cardIds->contains($dados['leadA2']->id));
        $this->assertTrue($cardIds->contains($dados['leadB1']->id));
        $this->assertTrue($cardIds->contains($dados['leadB2']->id));
        $this->assertTrue($cardIds->contains($dados['leadB3']->id));
        $this->assertTrue($cardIds->contains($dados['leadSemDono']->id));
    }

    public function test_usuario_com_permissao_view_all_visualiza_todos_os_leads(): void
    {
        $dados = $this->criarCenarioBase();
        $gestor = $this->criarUsuarioComViewAll();

        $componente = Livewire::actingAs($gestor)
            ->test(KanbanInteressados::class);

        $coluna = $componente->instance()->colunas()->firstWhere(fn (array $c) => $c['status']->id === $this->statusNovo->id);

        $this->assertNotNull($coluna);
        $this->assertSame(6, $coluna['total']);
        $this->assertEqualsWithDelta(7350.00, $coluna['valor'], 0.01);

        $cardIds = collect($coluna['leads'])->pluck('id');
        $this->assertCount(6, $cardIds);
    }

    public function test_consultor_comum_tentando_filtrar_outro_consultor_tem_filtro_ignorado_e_mantem_escopo(): void
    {
        $dados = $this->criarCenarioBase();

        $componente = Livewire::actingAs($dados['consultorA'])
            ->test(KanbanInteressados::class)
            ->set('filtroConsultorId', $dados['consultorB']->id);

        $coluna = $componente->instance()->colunas()->firstWhere(fn (array $c) => $c['status']->id === $this->statusNovo->id);

        $this->assertNotNull($coluna);
        $this->assertSame(3, $coluna['total']);
        $this->assertEqualsWithDelta(350.00, $coluna['valor'], 0.01);

        $cardIds = collect($coluna['leads'])->pluck('id');
        $this->assertFalse($cardIds->contains($dados['leadB1']->id));
        $this->assertFalse($cardIds->contains($dados['leadB2']->id));
        $this->assertFalse($cardIds->contains($dados['leadB3']->id));
    }

    public function test_super_admin_com_filtro_consultor_filtra_apenas_leads_do_consultor(): void
    {
        $dados = $this->criarCenarioBase();
        $superAdmin = $this->criarSuperAdmin();

        $componente = Livewire::actingAs($superAdmin)
            ->test(KanbanInteressados::class)
            ->set('filtroConsultorId', $dados['consultorB']->id);

        $coluna = $componente->instance()->colunas()->firstWhere(fn (array $c) => $c['status']->id === $this->statusNovo->id);

        $this->assertNotNull($coluna);
        $this->assertSame(3, $coluna['total']);
        $this->assertEqualsWithDelta(7000.00, $coluna['valor'], 0.01);

        $cardIds = collect($coluna['leads'])->pluck('id');
        $this->assertCount(3, $cardIds);
        $this->assertTrue($cardIds->contains($dados['leadB1']->id));
        $this->assertTrue($cardIds->contains($dados['leadB2']->id));
        $this->assertTrue($cardIds->contains($dados['leadB3']->id));
        $this->assertFalse($cardIds->contains($dados['leadA1']->id));
        $this->assertFalse($cardIds->contains($dados['leadSemDono']->id));
    }

    public function test_tem_mais_e_carregar_mais_respeitam_escopo_sem_vazar_outros_consultores(): void
    {
        config(['crm.kanban.cards_por_coluna' => 2]);
        $dados = $this->criarCenarioBase();

        $componente = Livewire::actingAs($dados['consultorA'])
            ->test(KanbanInteressados::class);

        $colunaInicial = $componente->instance()->colunas()->firstWhere(fn (array $c) => $c['status']->id === $this->statusNovo->id);
        $this->assertNotNull($colunaInicial);
        $this->assertSame(3, $colunaInicial['total']);
        $this->assertCount(2, $colunaInicial['leads']);
        $this->assertTrue($colunaInicial['tem_mais']);

        $cardIdsIniciais = collect($colunaInicial['leads'])->pluck('id');
        $this->assertFalse($cardIdsIniciais->contains($dados['leadB1']->id));
        $this->assertFalse($cardIdsIniciais->contains($dados['leadB2']->id));
        $this->assertFalse($cardIdsIniciais->contains($dados['leadB3']->id));

        $componente->call('carregarMais', $this->statusNovo->id);

        $colunaExpandida = $componente->instance()->colunas()->firstWhere(fn (array $c) => $c['status']->id === $this->statusNovo->id);
        $this->assertNotNull($colunaExpandida);
        $this->assertSame(3, $colunaExpandida['total']);
        $this->assertCount(3, $colunaExpandida['leads']);
        $this->assertFalse($colunaExpandida['tem_mais']);

        $cardIdsExpandidos = collect($colunaExpandida['leads'])->pluck('id');
        $this->assertTrue($cardIdsExpandidos->contains($dados['leadA1']->id));
        $this->assertTrue($cardIdsExpandidos->contains($dados['leadA2']->id));
        $this->assertTrue($cardIdsExpandidos->contains($dados['leadSemDono']->id));
        $this->assertFalse($cardIdsExpandidos->contains($dados['leadB1']->id));
        $this->assertFalse($cardIdsExpandidos->contains($dados['leadB2']->id));
        $this->assertFalse($cardIdsExpandidos->contains($dados['leadB3']->id));
    }

    public function test_coluna_final_nao_exibe_nem_soma_lead_finalizado_de_outro_consultor(): void
    {
        $consultorA = $this->criarConsultor('Consultor A');
        $consultorB = $this->criarConsultor('Consultor B');

        $leadFinalA = $this->criarLead($consultorA, 'Final A', 500.00, $this->statusMatriculado);
        $leadFinalB = $this->criarLead($consultorB, 'Final B', 1500.00, $this->statusMatriculado);

        $componente = Livewire::actingAs($consultorA)
            ->test(KanbanInteressados::class);

        $colunaFinal = $componente->instance()->colunas()->firstWhere(fn (array $c) => $c['status']->id === $this->statusMatriculado->id);

        $this->assertNotNull($colunaFinal);
        $this->assertSame(1, $colunaFinal['total']);
        $this->assertEqualsWithDelta(500.00, $colunaFinal['valor'], 0.01);

        $cardIds = collect($colunaFinal['leads'])->pluck('id');
        $this->assertCount(1, $cardIds);
        $this->assertTrue($cardIds->contains($leadFinalA->id));
        $this->assertFalse($cardIds->contains($leadFinalB->id));
    }

    public function test_lead_sem_consultor_permanece_visivel_para_qualquer_consultor(): void
    {
        $consultorA = $this->criarConsultor('Consultor A');
        $consultorB = $this->criarConsultor('Consultor B');

        $leadSemConsultor = $this->criarLead(null, 'Lead Órfão', 250.00);

        $componenteA = Livewire::actingAs($consultorA)->test(KanbanInteressados::class);
        $colunaA = $componenteA->instance()->colunas()->firstWhere(fn (array $c) => $c['status']->id === $this->statusNovo->id);
        $this->assertTrue(collect($colunaA['leads'])->pluck('id')->contains($leadSemConsultor->id));

        $componenteB = Livewire::actingAs($consultorB)->test(KanbanInteressados::class);
        $colunaB = $componenteB->instance()->colunas()->firstWhere(fn (array $c) => $c['status']->id === $this->statusNovo->id);
        $this->assertTrue(collect($colunaB['leads'])->pluck('id')->contains($leadSemConsultor->id));
    }
}
