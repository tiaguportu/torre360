<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RegimeContratacao;
use App\Enums\SituacaoConcessaoFerias;
use App\Filament\Pages\PainelFerias;
use App\Filament\Resources\Funcionarios\Pages\EditFuncionario;
use App\Filament\Resources\Funcionarios\RelationManagers\PeriodosFeriasRelationManager;
use App\Models\Funcionario;
use App\Models\PeriodoFerias;
use App\Models\Pessoa;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Services\FeriasVencimentoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Alerta de férias (melhoria da Onda 10): prazo de concessão (CLT, art. 134), risco de pagamento em dobro (art. 137)
 * e períodos aquisitivos esperados que ninguém cadastrou.
 *
 * "Hoje" nos testes é 21/10/2026.
 */
class FeriasVencimentoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-21 09:00:00'));
        Cache::forget(PainelFerias::CACHE_SELO);
    }

    private function servico(): FeriasVencimentoService
    {
        return app(FeriasVencimentoService::class);
    }

    private function funcionario(array $atributos = []): Funcionario
    {
        return Funcionario::factory()->create(array_merge([
            'pessoa_id' => Pessoa::factory()->create()->id,
            'regime' => RegimeContratacao::CLT->value,
            'data_admissao' => '2020-03-02',
        ], $atributos));
    }

    private function periodo(Funcionario $funcionario, string $inicio, string $fim, array $atributos = []): PeriodoFerias
    {
        return PeriodoFerias::factory()->create(array_merge([
            'funcionario_id' => $funcionario->id,
            'periodo_aquisitivo_inicio' => $inicio,
            'periodo_aquisitivo_fim' => $fim,
            'dias_direito' => 30,
            'dias_gozados' => 0,
        ], $atributos));
    }

    // ─── Situação de um período ──────────────────────────────────────

    public function test_limite_de_concessao_e_doze_meses_depois_do_fim_do_periodo_aquisitivo(): void
    {
        $periodo = $this->periodo($this->funcionario(), '2025-01-10', '2026-01-09');

        $this->assertSame('2027-01-09', $periodo->limiteDeConcessao()->toDateString());
    }

    public function test_periodo_ainda_em_aquisicao(): void
    {
        $periodo = $this->periodo($this->funcionario(), '2026-03-02', '2027-03-01');

        $this->assertSame(SituacaoConcessaoFerias::EmAquisicao, $this->servico()->avaliar($periodo)['situacao']);
    }

    public function test_periodo_a_conceder_com_prazo_folgado(): void
    {
        // Aquisitivo terminou em 01/03/2026: o prazo vai até 01/03/2027, a 131 dias de hoje.
        $periodo = $this->periodo($this->funcionario(), '2025-03-02', '2026-03-01');

        $avaliacao = $this->servico()->avaliar($periodo);

        $this->assertSame(SituacaoConcessaoFerias::AConceder, $avaliacao['situacao']);
        $this->assertSame(131, $avaliacao['dias_para_limite']);
        $this->assertSame(30, $avaliacao['saldo']);
        $this->assertSame(0, $avaliacao['dobro_dias']);
    }

    public function test_prazo_acabando_comeca_a_noventa_dias_do_limite(): void
    {
        // Limite 21/01/2027: 92 dias → ainda a conceder.
        $folgado = $this->periodo($this->funcionario(), '2025-01-22', '2026-01-21');
        $this->assertSame(92, $this->servico()->avaliar($folgado)['dias_para_limite']);
        $this->assertSame(SituacaoConcessaoFerias::AConceder, $this->servico()->avaliar($folgado)['situacao']);

        // Limite 19/01/2027: exatamente 90 dias → prazo acabando.
        $noventa = $this->periodo($this->funcionario(), '2025-01-20', '2026-01-19');
        $this->assertSame(90, $this->servico()->avaliar($noventa)['dias_para_limite']);
        $this->assertSame(SituacaoConcessaoFerias::AVencer, $this->servico()->avaliar($noventa)['situacao']);
    }

    public function test_no_ultimo_dia_do_prazo_ainda_nao_venceu_e_no_dia_seguinte_venceu(): void
    {
        // Limite = hoje (21/10/2026).
        $ultimoDia = $this->periodo($this->funcionario(), '2024-10-22', '2025-10-21');
        $this->assertSame(0, $this->servico()->avaliar($ultimoDia)['dias_para_limite']);
        $this->assertSame(SituacaoConcessaoFerias::AVencer, $this->servico()->avaliar($ultimoDia)['situacao']);

        // Limite = ontem (20/10/2026).
        $venceuOntem = $this->periodo($this->funcionario(), '2024-10-21', '2025-10-20');
        $this->assertSame(-1, $this->servico()->avaliar($venceuOntem)['dias_para_limite']);
        $this->assertSame(SituacaoConcessaoFerias::Vencida, $this->servico()->avaliar($venceuOntem)['situacao']);
    }

    public function test_periodo_vencido_expoe_os_dias_a_pagar_em_dobro(): void
    {
        $periodo = $this->periodo($this->funcionario(), '2024-10-21', '2025-10-20', ['dias_gozados' => 10, 'status' => 'parcial']);

        $avaliacao = $this->servico()->avaliar($periodo);

        $this->assertSame(SituacaoConcessaoFerias::Vencida, $avaliacao['situacao']);
        $this->assertSame(20, $avaliacao['saldo']);
        $this->assertSame(20, $avaliacao['dobro_dias']);
    }

    public function test_periodo_totalmente_gozado_no_prazo_e_fora_do_prazo(): void
    {
        $noPrazo = $this->periodo($this->funcionario(), '2024-10-21', '2025-10-20', [
            'dias_gozados' => 30, 'status' => 'gozado', 'data_inicio_gozo' => '2026-07-01', 'data_fim_gozo' => '2026-07-30',
        ]);
        $this->assertSame(SituacaoConcessaoFerias::Quitada, $this->servico()->avaliar($noPrazo)['situacao']);

        // Limite 31/01/2026; o gozo terminou em 10/02/2026, 10 dias depois.
        $atrasado = $this->periodo($this->funcionario(), '2024-02-01', '2025-01-31', [
            'dias_gozados' => 30, 'status' => 'gozado', 'data_inicio_gozo' => '2026-01-12', 'data_fim_gozo' => '2026-02-10',
        ]);
        $avaliacao = $this->servico()->avaliar($atrasado);
        $this->assertSame(SituacaoConcessaoFerias::QuitadaForaDoPrazo, $avaliacao['situacao']);
        $this->assertSame(10, $avaliacao['dias_fora_do_prazo']);
    }

    // ─── Períodos esperados sem registro ─────────────────────────────

    public function test_aponta_periodo_aquisitivo_que_ninguem_cadastrou(): void
    {
        // Admitido em 02/03/2024: 1º aquisitivo 02/03/2024 a 01/03/2025 (prazo acabou em 01/03/2026) e
        // 2º aquisitivo 02/03/2025 a 01/03/2026 (prazo até 01/03/2027). Nenhum cadastrado.
        $funcionario = $this->funcionario(['data_admissao' => '2024-03-02']);

        $esperados = $this->servico()->periodosSemRegistro($funcionario);

        $this->assertCount(2, $esperados);
        $this->assertSame('2024-03-02', $esperados[0]['inicio']->toDateString());
        $this->assertSame('2026-03-01', $esperados[0]['limite']->toDateString());
        $this->assertSame('2027-03-01', $esperados[1]['limite']->toDateString());
    }

    public function test_periodo_ja_cadastrado_nao_e_cobrado_e_historico_antigo_e_ignorado(): void
    {
        $funcionario = $this->funcionario(['data_admissao' => '2019-03-02']);
        $this->periodo($funcionario, '2024-03-02', '2025-03-01', ['dias_gozados' => 30, 'status' => 'gozado', 'data_fim_gozo' => '2025-12-20']);
        $this->periodo($funcionario, '2025-03-02', '2026-03-01');

        // Os aquisitivos de 2019 a 2023 tiveram o prazo vencido há mais de um ano: histórico, não alerta.
        $this->assertSame([], $this->servico()->periodosSemRegistro($funcionario->fresh()));
    }

    public function test_admissao_recente_nao_gera_periodo_esperado(): void
    {
        $funcionario = $this->funcionario(['data_admissao' => '2026-01-12']);

        $this->assertSame([], $this->servico()->periodosSemRegistro($funcionario));
    }

    // ─── Painel ──────────────────────────────────────────────────────

    public function test_painel_considera_so_clt_ativos_e_ordena_do_mais_grave(): void
    {
        $aVencer = $this->funcionario(['data_admissao' => '2023-12-04']);
        $this->periodo($aVencer, '2024-12-04', '2025-12-03'); // limite 03/12/2026: 43 dias
        $vencida = $this->funcionario(['data_admissao' => '2023-10-02']);
        $this->periodo($vencida, '2024-10-02', '2025-10-01'); // limite 01/10/2026: venceu há 20 dias
        $pj = $this->funcionario(['regime' => RegimeContratacao::PJ->value, 'data_admissao' => '2023-10-02']);
        $this->periodo($pj, '2024-10-02', '2025-10-01');
        $desligado = $this->funcionario(['data_admissao' => '2023-10-02', 'data_desligamento' => '2026-05-01']);
        $this->periodo($desligado, '2024-10-02', '2025-10-01');

        $painel = $this->servico()->painel();

        $this->assertNotContains($pj->id, $painel->pluck('funcionario.id')->all());
        $this->assertNotContains($desligado->id, $painel->pluck('funcionario.id')->all());

        $graves = $painel->filter(fn (array $l): bool => in_array($l['situacao'], [SituacaoConcessaoFerias::Vencida, SituacaoConcessaoFerias::AVencer], true))->values();
        $this->assertSame([$vencida->id, $aVencer->id], $graves->pluck('funcionario.id')->all());
        $this->assertSame(SituacaoConcessaoFerias::Vencida, $painel->first()['situacao'], 'O mais grave vem primeiro.');
    }

    public function test_resumo_conta_vencidas_a_vencer_e_sem_registro(): void
    {
        // Vencida (período cadastrado) + o aquisitivo seguinte, que ninguém cadastrou.
        $vencida = $this->funcionario(['data_admissao' => '2023-10-02']);
        $this->periodo($vencida, '2024-10-02', '2025-10-01');
        // Admitido em 2024: dois aquisitivos sem registro.
        $this->funcionario(['data_admissao' => '2024-03-02']);

        $resumo = $this->servico()->resumo();

        $this->assertSame(1, $resumo['vencida']);
        $this->assertSame(0, $resumo['a_vencer']);
        $this->assertSame(3, $resumo['sem_registro']);
    }

    // ─── Telas e permissão ───────────────────────────────────────────

    private function usuario(string $papel, array $permissoes): User
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        foreach ($permissoes as $permissao) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'web']));
        }

        $user = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $user->assignRole($papel);
        session(['active_role' => $papel]);

        return $user;
    }

    public function test_painel_mostra_os_alertas_so_para_quem_tem_a_permissao(): void
    {
        $vencida = $this->funcionario(['data_admissao' => '2023-10-02']);
        $this->periodo($vencida, '2024-10-02', '2025-10-01');

        $rh = $this->usuario('admin', ['VerPainelFerias:Funcionario']);
        Livewire::actingAs($rh)
            ->test(PainelFerias::class)
            ->assertSee($vencida->pessoa->nome)
            ->assertSee('Vencida')
            ->set('filtro', 'quitada')
            ->assertDontSee($vencida->pessoa->nome);

        $professor = $this->usuario('professor', []);
        Livewire::actingAs($professor)->test(PainelFerias::class)->assertForbidden();
    }

    public function test_selo_do_menu_conta_vencidas_e_a_vencer(): void
    {
        $vencida = $this->funcionario(['data_admissao' => '2023-10-02']);
        $this->periodo($vencida, '2024-10-02', '2025-10-01');

        $this->actingAs($this->usuario('admin', ['VerPainelFerias:Funcionario']));
        $this->assertSame('1', PainelFerias::getNavigationBadge());

        Cache::forget(PainelFerias::CACHE_SELO);
        $this->actingAs($this->usuario('secretaria', []));
        $this->assertNull(PainelFerias::getNavigationBadge());
    }

    public function test_aba_de_ferias_mostra_o_prazo_so_para_clt(): void
    {
        $rh = $this->usuario('admin', ['ViewAny:Funcionario', 'View:Funcionario', 'Update:Funcionario']);
        $clt = $this->funcionario();
        $this->periodo($clt, '2024-10-02', '2025-10-01');
        $pj = $this->funcionario(['regime' => RegimeContratacao::PJ->value]);
        $this->periodo($pj, '2024-10-02', '2025-10-01');

        Livewire::actingAs($rh)
            ->test(PeriodosFeriasRelationManager::class, ['ownerRecord' => $clt, 'pageClass' => EditFuncionario::class])
            ->assertSee('01/10/2026')
            ->assertSee('Vencida');

        Livewire::actingAs($rh)
            ->test(PeriodosFeriasRelationManager::class, ['ownerRecord' => $pj, 'pageClass' => EditFuncionario::class])
            ->assertDontSee('Vencida');
    }

    // ─── Aviso semanal ───────────────────────────────────────────────

    public function test_aviso_semanal_vai_para_a_administracao_sem_nome_de_empregado(): void
    {
        Notification::fake();
        $vencida = $this->funcionario(['data_admissao' => '2023-10-02']);
        $this->periodo($vencida, '2024-10-02', '2025-10-01');
        $admin = $this->usuario('admin', ['VerPainelFerias:Funcionario']);
        $professor = $this->usuario('professor', []);

        $this->artisan('rh:avisar-ferias')->assertSuccessful();

        Notification::assertSentTo($admin, SystemNotification::class, fn (SystemNotification $n): bool => str_contains($n->body, '1 vencida(s)') && ! str_contains($n->body, (string) $vencida->pessoa->nome));
        Notification::assertNotSentTo($professor, SystemNotification::class);
    }

    public function test_sem_pendencia_nao_envia_aviso(): void
    {
        Notification::fake();
        $this->usuario('admin', ['VerPainelFerias:Funcionario']);

        $this->artisan('rh:avisar-ferias')->assertSuccessful();

        Notification::assertNothingSent();
    }
}
