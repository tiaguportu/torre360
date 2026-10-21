<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SituacaoPlanoManutencao;
use App\Enums\StatusBemPatrimonial;
use App\Enums\StatusExecucaoManutencao;
use App\Exceptions\ManutencaoPreventivaException;
use App\Filament\Resources\PlanoManutencaos\Pages\CreatePlanoManutencao;
use App\Filament\Resources\PlanoManutencaos\Pages\ListPlanoManutencaos;
use App\Models\BemPatrimonial;
use App\Models\CategoriaOs;
use App\Models\ExecucaoManutencao;
use App\Models\OrdemServico;
use App\Models\PlanoManutencao;
use App\Models\Sala;
use App\Models\User;
use App\Services\ManutencaoPreventivaService;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Manutenção preventiva (Onda 39): planos recorrentes que abrem ordens de serviço na janela de antecedência, fecham o
 * ciclo na execução e avançam o próximo vencimento. O relógio é fixado em 20/10/2026.
 */
class ManutencaoPreventivaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-20 08:00:00'));
    }

    private function servico(): ManutencaoPreventivaService
    {
        return app(ManutencaoPreventivaService::class);
    }

    /**
     * @param  list<string>  $acoes
     */
    private function autenticarComo(string $papel, array $acoes): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        foreach ($acoes as $acao) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => "{$acao}:PlanoManutencao", 'guard_name' => 'web']));
        }

        $user = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $user->assignRole($papel);
        $this->actingAs($user);
        session(['active_role' => $papel]);

        return $user;
    }

    private function gestor(): User
    {
        return $this->autenticarComo('secretaria', ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny', 'GerarOrdem', 'RegistrarExecucao']);
    }

    // ─── Geração das ordens ──────────────────────────────────────────

    public function test_plano_dentro_da_janela_abre_a_ordem_de_servico_com_os_dados_do_plano(): void
    {
        $categoria = CategoriaOs::create(['nome' => 'Segurança', 'cor' => '#ff0000']);
        $sala = Sala::factory()->create(['nome' => 'Laboratório 2']);
        $plano = PlanoManutencao::factory()->create([
            'titulo' => 'Revisão dos extintores',
            'sala_id' => $sala->id,
            'proxima_execucao' => '2026-10-25',
            'antecedencia_dias' => 7,
            'prioridade' => 'Alta',
            'categoria_os_id' => $categoria->id,
            'checklist' => ['Conferir a validade', 'Verificar o lacre'],
        ]);

        $abertas = $this->servico()->gerarOrdensPendentes();

        $this->assertSame(1, $abertas);
        $execucao = $plano->execucoes()->first();
        $ordem = $execucao->ordemServico;

        $this->assertSame(StatusExecucaoManutencao::Prevista, $execucao->status);
        $this->assertSame('2026-10-25', $execucao->data_prevista->toDateString());
        $this->assertSame('Manutenção preventiva: Revisão dos extintores', $ordem->titulo);
        $this->assertSame('Aberta', $ordem->status);
        $this->assertSame('Alta', $ordem->prioridade);
        $this->assertSame('2026-10-25', $ordem->prazo_conclusao->toDateString());
        $this->assertStringContainsString('Laboratório 2', $ordem->descricao);
        $this->assertStringContainsString('- Conferir a validade', $ordem->descricao);
        $this->assertSame([$categoria->id], $ordem->categorias->pluck('id')->all());
    }

    public function test_plano_fora_da_janela_nao_abre_ordem_e_na_janela_abre(): void
    {
        PlanoManutencao::factory()->create(['proxima_execucao' => '2026-11-20', 'antecedencia_dias' => 7]);

        $this->assertSame(0, $this->servico()->gerarOrdensPendentes());
        $this->assertSame(0, OrdemServico::count());

        // 13/11 é o primeiro dia da janela (20/11 − 7).
        $this->assertSame(0, $this->servico()->gerarOrdensPendentes(Carbon::parse('2026-11-12')));
        $this->assertSame(1, $this->servico()->gerarOrdensPendentes(Carbon::parse('2026-11-13')));
    }

    public function test_gerar_duas_vezes_nao_duplica_a_ordem(): void
    {
        PlanoManutencao::factory()->create(['proxima_execucao' => '2026-10-22']);

        $this->assertSame(1, $this->servico()->gerarOrdensPendentes());
        $this->assertSame(0, $this->servico()->gerarOrdensPendentes());
        $this->assertSame(1, OrdemServico::count());
        $this->assertSame(1, ExecucaoManutencao::count());
    }

    public function test_plano_inativo_ou_de_bem_baixado_nao_gera_ordem(): void
    {
        PlanoManutencao::factory()->inativo()->create(['proxima_execucao' => '2026-10-22']);

        $bem = BemPatrimonial::create(['descricao' => 'Projetor antigo', 'numero_patrimonio' => 'P-1', 'categoria' => 'Equipamento', 'status' => StatusBemPatrimonial::Baixado]);
        PlanoManutencao::factory()->create(['sala_id' => null, 'bem_patrimonial_id' => $bem->id, 'proxima_execucao' => '2026-10-22']);

        $this->assertSame(0, $this->servico()->gerarOrdensPendentes());
        $this->assertSame(0, OrdemServico::count());
    }

    public function test_gerar_ordem_agora_ignora_a_antecedencia_mas_nao_duplica(): void
    {
        $plano = PlanoManutencao::factory()->create(['proxima_execucao' => '2026-12-30', 'antecedencia_dias' => 7]);

        $execucao = $this->servico()->gerarOrdem($plano);

        $this->assertNotNull($execucao->ordem_servico_id);

        $this->expectException(ManutencaoPreventivaException::class);
        $this->expectExceptionMessage('Já existe uma execução');

        $this->servico()->gerarOrdem($plano->fresh());
    }

    // ─── Registro da execução ────────────────────────────────────────

    public function test_registrar_execucao_conclui_a_ordem_e_avanca_o_plano_a_partir_da_data_feita(): void
    {
        $plano = PlanoManutencao::factory()->create(['proxima_execucao' => '2026-10-22', 'periodicidade_dias' => 90]);
        $this->servico()->gerarOrdensPendentes();
        $gestor = $this->gestor();

        $execucao = $this->servico()->registrarExecucao(
            $plano,
            Carbon::parse('2026-10-19'),
            [['item' => 'Verificar o estado geral', 'ok' => true], ['item' => 'Registrar anomalias', 'ok' => false, 'observacao' => 'Mangueira rachada']],
            'Trocada a mangueira.',
            $gestor,
        );

        $this->assertSame(StatusExecucaoManutencao::Concluida, $execucao->status);
        $this->assertSame('2026-10-19', $execucao->concluida_em->toDateString());
        $this->assertSame($gestor->id, $execucao->registrado_por_user_id);
        $this->assertFalse($execucao->checklist_resultado[1]['ok']);

        $ordem = $execucao->ordemServico->fresh();
        $this->assertSame('Concluída', $ordem->status);
        $this->assertSame(100, $ordem->percentual_conclusao);

        $plano->refresh();
        $this->assertSame('2026-10-19', $plano->ultima_execucao->toDateString());
        $this->assertSame('2027-01-17', $plano->proxima_execucao->toDateString(), '19/10 + 90 dias.');
    }

    public function test_registrar_sem_ordem_aberta_cria_a_execucao_ja_concluida(): void
    {
        $plano = PlanoManutencao::factory()->create(['proxima_execucao' => '2026-12-01', 'periodicidade_dias' => 30]);

        $execucao = $this->servico()->registrarExecucao($plano, Carbon::parse('2026-10-20'));

        $this->assertNull($execucao->ordem_servico_id);
        $this->assertSame(StatusExecucaoManutencao::Concluida, $execucao->status);
        $this->assertSame('2026-11-19', $plano->fresh()->proxima_execucao->toDateString());
    }

    public function test_execucao_no_futuro_ou_em_plano_inativo_e_recusada(): void
    {
        $plano = PlanoManutencao::factory()->create();

        try {
            $this->servico()->registrarExecucao($plano, Carbon::parse('2026-10-25'));
            $this->fail('Data futura deveria ser recusada.');
        } catch (ManutencaoPreventivaException $e) {
            $this->assertStringContainsString('futura', $e->getMessage());
        }

        $this->expectException(ManutencaoPreventivaException::class);
        $this->servico()->registrarExecucao(PlanoManutencao::factory()->inativo()->create(), Carbon::parse('2026-10-20'));
    }

    // ─── Sincronização com as ordens de serviço ──────────────────────

    public function test_concluir_a_ordem_na_tela_de_ordens_fecha_a_execucao_e_avanca_o_plano(): void
    {
        $plano = PlanoManutencao::factory()->create(['proxima_execucao' => '2026-10-22', 'periodicidade_dias' => 60]);
        $this->servico()->gerarOrdensPendentes();

        $execucao = $plano->execucoes()->first();
        $execucao->ordemServico->update(['status' => 'Concluída']);

        $this->assertSame(1, $this->servico()->sincronizarComOrdensDeServico());

        $this->assertSame(StatusExecucaoManutencao::Concluida, $execucao->fresh()->status);
        $this->assertSame('2026-12-19', $plano->fresh()->proxima_execucao->toDateString(), '20/10 + 60 dias.');
    }

    public function test_ordem_cancelada_cancela_a_execucao_e_nao_reabre_ordem_para_o_mesmo_vencimento(): void
    {
        $plano = PlanoManutencao::factory()->create(['proxima_execucao' => '2026-10-22']);
        $this->servico()->gerarOrdensPendentes();

        $plano->execucoes()->first()->ordemServico->update(['status' => 'Cancelada']);
        $this->servico()->sincronizarComOrdensDeServico();

        $this->assertSame(StatusExecucaoManutencao::Cancelada, $plano->execucoes()->first()->status);
        $this->assertSame(0, $this->servico()->gerarOrdensPendentes(), 'O vencimento já tem execução (cancelada): não abre outra ordem.');
        $this->assertSame(SituacaoPlanoManutencao::AVencer, $plano->fresh()->situacao());
    }

    public function test_sincronizar_nao_derruba_quando_o_plano_foi_desativado(): void
    {
        $plano = PlanoManutencao::factory()->create(['proxima_execucao' => '2026-10-22']);
        $this->servico()->gerarOrdensPendentes();
        $plano->execucoes()->first()->ordemServico->update(['status' => 'Concluída']);
        $plano->update(['ativo' => false]);

        $this->assertSame(0, $this->servico()->sincronizarComOrdensDeServico());
    }

    // ─── Reprogramar e situação ──────────────────────────────────────

    public function test_reprogramar_cancela_a_execucao_aberta_e_a_nova_data_gera_nova_ordem(): void
    {
        $plano = PlanoManutencao::factory()->create(['proxima_execucao' => '2026-10-22', 'antecedencia_dias' => 7]);
        $this->servico()->gerarOrdensPendentes();

        $this->servico()->reprogramar($plano, Carbon::parse('2026-11-30'));

        $this->assertSame(StatusExecucaoManutencao::Cancelada, $plano->execucoes()->first()->status);
        $this->assertSame('2026-11-30', $plano->fresh()->proxima_execucao->toDateString());
        $this->assertSame(1, $this->servico()->gerarOrdensPendentes(Carbon::parse('2026-11-23')));
    }

    public function test_reprogramar_para_o_passado_e_recusado(): void
    {
        $this->expectException(ManutencaoPreventivaException::class);

        $this->servico()->reprogramar(PlanoManutencao::factory()->create(), Carbon::parse('2026-10-01'));
    }

    public function test_situacao_do_plano_em_relacao_ao_vencimento(): void
    {
        $this->assertSame(SituacaoPlanoManutencao::Vencido, PlanoManutencao::factory()->make(['proxima_execucao' => '2026-10-19'])->situacao());
        $this->assertSame(SituacaoPlanoManutencao::AVencer, PlanoManutencao::factory()->make(['proxima_execucao' => '2026-10-20'])->situacao());
        $this->assertSame(SituacaoPlanoManutencao::AVencer, PlanoManutencao::factory()->make(['proxima_execucao' => '2026-11-19'])->situacao());
        $this->assertSame(SituacaoPlanoManutencao::EmDia, PlanoManutencao::factory()->make(['proxima_execucao' => '2026-11-20'])->situacao());
        $this->assertSame(SituacaoPlanoManutencao::Inativo, PlanoManutencao::factory()->inativo()->make(['proxima_execucao' => '2026-10-01'])->situacao());
    }

    // ─── Agendador ───────────────────────────────────────────────────

    public function test_comando_abre_as_ordens_e_esta_agendado_diariamente(): void
    {
        PlanoManutencao::factory()->create(['proxima_execucao' => '2026-10-22']);

        $this->artisan('manutencao:gerar-ordens')
            ->expectsOutputToContain('1 ordem(ns) aberta(s)')
            ->assertSuccessful();

        $evento = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'manutencao:gerar-ordens'));
        $this->assertNotNull($evento, 'O comando precisa estar no agendador.');
        $this->assertSame('15 6 * * *', $evento->expression);
    }

    // ─── Telas ───────────────────────────────────────────────────────

    public function test_criar_plano_exige_bem_ou_espaco(): void
    {
        $this->gestor();

        Livewire::test(CreatePlanoManutencao::class)
            ->fillForm([
                'titulo' => 'Revisão elétrica',
                'prioridade' => 'Média',
                'periodicidade_dias' => 180,
                'proxima_execucao' => '2026-12-01',
                'antecedencia_dias' => 7,
            ])
            ->call('create')
            ->assertHasFormErrors(['bem_patrimonial_id' => 'required_without', 'sala_id' => 'required_without']);

        $this->assertSame(0, PlanoManutencao::count());
    }

    public function test_criar_plano_pelo_formulario_com_checklist(): void
    {
        $gestor = $this->gestor();
        $sala = Sala::factory()->create();

        Livewire::test(CreatePlanoManutencao::class)
            ->fillForm([
                'titulo' => 'Revisão elétrica',
                'sala_id' => $sala->id,
                'prioridade' => 'Alta',
                'periodicidade_dias' => 180,
                'proxima_execucao' => '2026-12-01',
                'antecedencia_dias' => 10,
                'checklist' => [['item' => 'Testar os disjuntores'], ['item' => 'Conferir o aterramento']],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $plano = PlanoManutencao::firstOrFail();
        $this->assertSame($gestor->id, $plano->criado_por_user_id);
        $this->assertSame(['Testar os disjuntores', 'Conferir o aterramento'], $plano->checklist);
    }

    public function test_acoes_da_tabela_respeitam_a_permissao(): void
    {
        $plano = PlanoManutencao::factory()->create(['proxima_execucao' => '2026-10-25']);

        $this->autenticarComo('coordenador', ['ViewAny', 'View']);
        Livewire::test(ListPlanoManutencaos::class)
            ->assertTableActionHidden('gerarOrdem', $plano)
            ->assertTableActionHidden('registrarExecucao', $plano);

        $this->gestor();
        Livewire::test(ListPlanoManutencaos::class)
            ->assertTableActionVisible('gerarOrdem', $plano)
            ->callTableAction('gerarOrdem', $plano)
            ->assertNotified();

        $this->assertSame(1, OrdemServico::count());
    }

    public function test_registrar_execucao_pela_tabela_avanca_o_plano(): void
    {
        $this->gestor();
        $plano = PlanoManutencao::factory()->create(['proxima_execucao' => '2026-10-25', 'periodicidade_dias' => 30, 'checklist' => ['Verificar o estado geral']]);

        Livewire::test(ListPlanoManutencaos::class)
            ->callTableAction('registrarExecucao', $plano, [
                'data' => '2026-10-20',
                'checklist' => [['item' => 'Verificar o estado geral', 'ok' => false, 'observacao' => 'Desgaste']],
                'observacoes' => 'Peça encomendada',
            ])
            ->assertNotified('Execução registrada');

        $plano->refresh();
        $this->assertSame('2026-11-19', $plano->proxima_execucao->toDateString());
        $execucao = $plano->execucoes()->first();
        $this->assertFalse($execucao->checklist_resultado[0]['ok']);
        $this->assertSame('Peça encomendada', $execucao->observacoes);
    }

    public function test_filtro_de_situacao_separa_vencidos_de_a_vencer(): void
    {
        $this->gestor();
        $vencido = PlanoManutencao::factory()->create(['proxima_execucao' => '2026-10-10']);
        $aVencer = PlanoManutencao::factory()->create(['proxima_execucao' => '2026-11-01']);
        $emDia = PlanoManutencao::factory()->create(['proxima_execucao' => '2027-03-01']);

        Livewire::test(ListPlanoManutencaos::class)
            ->filterTable('situacao', SituacaoPlanoManutencao::Vencido->value)
            ->assertCanSeeTableRecords([$vencido])
            ->assertCanNotSeeTableRecords([$aVencer, $emDia])
            ->filterTable('situacao', SituacaoPlanoManutencao::EmDia->value)
            ->assertCanSeeTableRecords([$emDia])
            ->assertCanNotSeeTableRecords([$vencido, $aVencer]);
    }
}
