<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ModoPonto;
use App\Enums\StatusTratamentoPonto;
use App\Enums\TipoAcordoBancoHoras;
use App\Enums\TipoTratamentoPonto;
use App\Filament\Pages\BancoDeHoras;
use App\Filament\Pages\EspelhoDePonto;
use App\Filament\Pages\RegistrarPonto;
use App\Filament\Resources\Funcionarios\Pages\EditFuncionario;
use App\Filament\Resources\Funcionarios\RelationManagers\AcordosBancoHorasRelationManager;
use App\Filament\Resources\Funcionarios\RelationManagers\JornadasPontoRelationManager;
use App\Filament\Resources\PontoConfiguracaos\Pages\CreatePontoConfiguracao;
use App\Filament\Resources\PontoConfiguracaos\Pages\EditPontoConfiguracao;
use App\Filament\Resources\PontoConfiguracaos\Pages\ListPontoConfiguracaos;
use App\Filament\Resources\PontoTratamentos\Pages\CreatePontoTratamento;
use App\Filament\Resources\PontoTratamentos\Pages\ListPontoTratamentos;
use App\Models\Funcionario;
use App\Models\Pessoa;
use App\Models\PontoAcordoBancoHoras;
use App\Models\PontoBancoHorasLancamento;
use App\Models\PontoConfiguracao;
use App\Models\PontoJornada;
use App\Models\PontoRegistro;
use App\Models\PontoTratamento;
use App\Models\Unidade;
use App\Models\User;
use App\Services\Ponto\BancoHorasService;
use App\Services\Ponto\HoraLegalDesativada;
use App\Services\Ponto\HoraLegalProvider;
use App\Services\Ponto\PontoRegistroService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Telas do ponto eletrônico (Onda 41): registrar ponto, espelho, banco de horas, ajustes, configuração e as abas da
 * ficha do funcionário. Foco em acesso (cada um vê o seu), ações e bloqueios.
 */
class PontoTelasTest extends TestCase
{
    use RefreshDatabase;

    private Unidade $unidade;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(HoraLegalProvider::class, new HoraLegalDesativada);
        $this->travelTo(Carbon::parse('2026-10-20 09:00:00'));

        $this->unidade = Unidade::create(['nome' => 'Unidade Central', 'situacao_funcionamento' => '1']);
        PontoConfiguracao::factory()->create(['unidade_id' => $this->unidade->id]);
    }

    /**
     * Usuário com papel, permissões e (opcionalmente) cadastro de funcionário ligado à sua pessoa.
     *
     * @param  list<string>  $permissoes
     * @return array{user: User, func: ?Funcionario}
     */
    private function usuario(string $papel, array $permissoes, bool $comFuncionario = true): array
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        foreach ($permissoes as $permissao) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'web']));
        }

        $user = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $user->assignRole($papel);
        session(['active_role' => $papel]);

        $func = null;
        if ($comFuncionario) {
            $pessoa = Pessoa::factory()->create();
            $user->pessoas()->attach($pessoa->id);
            $func = Funcionario::factory()->create(['pessoa_id' => $pessoa->id, 'unidade_id' => $this->unidade->id, 'data_admissao' => '2025-01-10']);
        }

        return ['user' => $user, 'func' => $func];
    }

    private function colega(): Funcionario
    {
        return Funcionario::factory()->create(['pessoa_id' => Pessoa::factory()->create()->id, 'unidade_id' => $this->unidade->id, 'data_admissao' => '2025-01-10']);
    }

    private function professor(): array
    {
        return $this->usuario('professor', ['Registrar:PontoRegistro', 'ViewAny:PontoTratamento', 'View:PontoTratamento', 'Create:PontoTratamento']);
    }

    private function rh(): array
    {
        return $this->usuario('admin', [
            'Registrar:PontoRegistro', 'ViewAny:PontoRegistro', 'View:PontoRegistro', 'VerEspelhoDeTodos:PontoRegistro',
            'ViewAny:PontoTratamento', 'View:PontoTratamento', 'Create:PontoTratamento', 'Aprovar:PontoTratamento',
            'ViewAny:PontoConfiguracao', 'View:PontoConfiguracao', 'Create:PontoConfiguracao', 'Update:PontoConfiguracao',
            'ViewAny:PontoJornada', 'View:PontoJornada', 'Create:PontoJornada', 'Update:PontoJornada',
            'ViewAny:PontoAcordoBancoHoras', 'View:PontoAcordoBancoHoras', 'Create:PontoAcordoBancoHoras', 'Update:PontoAcordoBancoHoras',
            'VerTodos:PontoBancoHorasLancamento', 'Ajustar:PontoBancoHorasLancamento', 'Compensar:PontoBancoHorasLancamento',
        ]);
    }

    private function marcar(Funcionario $func, string $dia, array $horas): void
    {
        foreach ($horas as $hora) {
            $this->travelTo(Carbon::parse("{$dia} {$hora}:00"));
            app(PontoRegistroService::class)->registrar($func);
        }

        $this->travelTo(Carbon::parse('2026-10-20 09:00:00'));
    }

    // ─── Registrar ponto ─────────────────────────────────────────────

    public function test_trabalhador_registra_o_ponto_pela_tela_e_ve_a_marcacao(): void
    {
        ['user' => $user, 'func' => $func] = $this->professor();

        Livewire::actingAs($user)
            ->test(RegistrarPonto::class)
            ->assertSee($func->pessoa->nome)
            ->call('registrar')
            ->assertNotified()
            ->assertSee('09:00:00')
            ->assertSee('NSR 1');

        $registro = PontoRegistro::first();
        $this->assertSame($func->id, $registro->funcionario_id);
        $this->assertSame('2026-10-20 09:00:00', $registro->momento->format('Y-m-d H:i:s'));
    }

    public function test_tela_de_registro_exige_cadastro_de_funcionario_e_permissao(): void
    {
        ['user' => $semCadastro] = $this->usuario('professor', ['Registrar:PontoRegistro'], comFuncionario: false);
        Livewire::actingAs($semCadastro)->test(RegistrarPonto::class)->assertForbidden();

        ['user' => $semPermissao] = $this->usuario('secretaria', []);
        Livewire::actingAs($semPermissao)->test(RegistrarPonto::class)->assertForbidden();

        $this->assertSame(0, PontoRegistro::count());
    }

    public function test_tela_de_registro_avisa_quando_a_unidade_nao_tem_ponto_configurado(): void
    {
        ['user' => $user, 'func' => $func] = $this->professor();
        PontoConfiguracao::where('unidade_id', $this->unidade->id)->update(['ativo' => false]);

        Livewire::actingAs($user)
            ->test(RegistrarPonto::class)
            ->assertSee('ainda não foi configurado')
            ->call('registrar')
            ->assertNotified('Não foi possível registrar');

        $this->assertSame(0, PontoRegistro::count());
        $this->assertNotNull($func);
    }

    // ─── Espelho ─────────────────────────────────────────────────────

    public function test_trabalhador_ve_so_o_proprio_espelho_mesmo_forjando_o_filtro(): void
    {
        ['user' => $user, 'func' => $meu] = $this->professor();
        $outro = $this->colega();
        $this->marcar($meu, '2026-10-05', ['08:00', '12:00', '13:00', '17:00']);
        $this->marcar($outro, '2026-10-06', ['07:30', '11:30']);

        $componente = Livewire::actingAs($user)->test(EspelhoDePonto::class)->assertSee('08:00')->assertDontSee('07:30');

        $componente->set('filtros.funcionario_id', $outro->id)->assertSee('08:00')->assertDontSee('07:30');
        $this->assertSame(0, Activity::where('log_name', 'ponto')->where('description', 'like', 'Consulta%')->count());
    }

    public function test_rh_escolhe_o_funcionario_e_a_consulta_fica_registrada(): void
    {
        ['user' => $rh] = $this->rh();
        $alvo = $this->colega();
        $this->marcar($alvo, '2026-10-06', ['07:30', '11:30']);
        Activity::query()->delete();

        Livewire::actingAs($rh)
            ->test(EspelhoDePonto::class)
            ->set('filtros.funcionario_id', $alvo->id)
            ->assertSee('07:30');

        $log = Activity::where('log_name', 'ponto')->where('description', 'like', 'Consulta%')->first();
        $this->assertNotNull($log);
        $this->assertSame($alvo->id, $log->properties['funcionario_id']);
        $this->assertStringNotContainsString('07:30', $log->toJson(), 'O log não guarda o conteúdo do ponto.');
    }

    public function test_espelho_nega_quem_nao_tem_cadastro_nem_permissao(): void
    {
        ['user' => $user] = $this->usuario('secretaria', [], comFuncionario: false);

        Livewire::actingAs($user)->test(EspelhoDePonto::class)->assertForbidden();
    }

    // ─── Banco de horas ──────────────────────────────────────────────

    public function test_trabalhador_ve_o_proprio_saldo_e_nao_tem_as_acoes_do_rh(): void
    {
        ['user' => $user, 'func' => $func] = $this->professor();
        $outro = $this->colega();
        PontoAcordoBancoHoras::factory()->create(['funcionario_id' => $func->id]);
        PontoAcordoBancoHoras::factory()->create(['funcionario_id' => $outro->id]);
        app(BancoHorasService::class)->ajustarManualmente($func, Carbon::parse('2026-10-05'), 90, 'Saldo trazido do sistema antigo', $user);
        app(BancoHorasService::class)->ajustarManualmente($outro, Carbon::parse('2026-10-05'), 600, 'Saldo trazido do sistema antigo', $user);

        Livewire::actingAs($user)
            ->test(BancoDeHoras::class)
            ->assertSee('01:30')
            ->assertDontSee('10:00')
            ->assertActionHidden('compensar')
            ->assertActionHidden('ajustar')
            ->set('filtros.funcionario_id', $outro->id)
            ->assertDontSee('10:00');
    }

    public function test_rh_registra_folga_e_ajuste_pela_tela(): void
    {
        ['user' => $rh] = $this->rh();
        $alvo = $this->colega();
        PontoAcordoBancoHoras::factory()->create(['funcionario_id' => $alvo->id]);
        app(BancoHorasService::class)->ajustarManualmente($alvo, Carbon::parse('2026-10-05'), 240, 'Saldo trazido do sistema antigo', $rh);

        Livewire::actingAs($rh)
            ->test(BancoDeHoras::class)
            ->set('filtros.funcionario_id', $alvo->id)
            ->callAction('compensar', ['data' => '2026-10-19', 'horas' => 1.5, 'observacao' => 'Folga combinada'])
            ->assertNotified('Folga registrada')
            ->callAction('ajustar', ['data' => '2026-10-19', 'horas' => -0.5, 'motivo' => 'Atraso não registrado em setembro'])
            ->assertNotified('Ajuste registrado');

        $this->assertSame(240 - 90 - 30, app(BancoHorasService::class)->saldo($alvo));
        $this->assertSame(3, PontoBancoHorasLancamento::where('funcionario_id', $alvo->id)->count());
    }

    public function test_compensacao_sem_saldo_vira_aviso_e_nao_grava(): void
    {
        ['user' => $rh] = $this->rh();
        $alvo = $this->colega();
        PontoAcordoBancoHoras::factory()->create(['funcionario_id' => $alvo->id]);

        Livewire::actingAs($rh)
            ->test(BancoDeHoras::class)
            ->set('filtros.funcionario_id', $alvo->id)
            ->callAction('compensar', ['data' => '2026-10-19', 'horas' => 2, 'observacao' => null])
            ->assertNotified('Não foi possível registrar');

        $this->assertSame(0, PontoBancoHorasLancamento::count());
    }

    // ─── Ajustes de ponto ────────────────────────────────────────────

    public function test_trabalhador_pede_ajuste_que_nasce_pendente(): void
    {
        ['user' => $user, 'func' => $func] = $this->professor();

        Livewire::actingAs($user)
            ->test(CreatePontoTratamento::class)
            ->fillForm([
                'funcionario_id' => $func->id,
                'tipo' => TipoTratamentoPonto::Inclusao->value,
                'data' => '2026-10-19',
                'momento' => '2026-10-19 17:00:00',
                'motivo' => 'Esqueci de marcar a saída do dia',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $tratamento = PontoTratamento::first();
        $this->assertSame(StatusTratamentoPonto::Pendente, $tratamento->status);
        $this->assertSame($func->id, $tratamento->funcionario_id);
        $this->assertSame($user->id, $tratamento->solicitado_por_user_id);
    }

    public function test_trabalhador_nao_pede_ajuste_do_ponto_de_outra_pessoa(): void
    {
        ['user' => $user] = $this->professor();
        $outro = $this->colega();

        Livewire::actingAs($user)
            ->test(CreatePontoTratamento::class)
            ->fillForm([
                'funcionario_id' => $outro->id,
                'tipo' => TipoTratamentoPonto::Abono->value,
                'data' => '2026-10-19',
                'minutos_abono' => 480,
                'motivo' => 'Tentando abonar o dia de outra pessoa',
            ])
            ->call('create')
            ->assertForbidden();

        $this->assertSame(0, PontoTratamento::count());
    }

    public function test_rh_lanca_ajuste_aprovado_para_outro_mas_o_proprio_ajuste_fica_pendente(): void
    {
        ['user' => $rh, 'func' => $meu] = $this->rh();
        $outro = $this->colega();

        $dados = fn (Funcionario $f) => [
            'funcionario_id' => $f->id,
            'tipo' => TipoTratamentoPonto::Abono->value,
            'data' => '2026-10-19',
            'minutos_abono' => 480,
            'motivo' => 'Atestado apresentado ao RH',
        ];

        Livewire::actingAs($rh)->test(CreatePontoTratamento::class)->fillForm($dados($outro))->call('create')->assertHasNoFormErrors();
        Livewire::actingAs($rh)->test(CreatePontoTratamento::class)->fillForm($dados($meu))->call('create')->assertHasNoFormErrors();

        $this->assertSame(StatusTratamentoPonto::Aprovado, PontoTratamento::where('funcionario_id', $outro->id)->first()->status);
        $this->assertSame(StatusTratamentoPonto::Pendente, PontoTratamento::where('funcionario_id', $meu->id)->first()->status);
    }

    public function test_regra_de_negocio_do_ajuste_vira_aviso_na_tela(): void
    {
        ['user' => $user, 'func' => $func] = $this->professor();

        Livewire::actingAs($user)
            ->test(CreatePontoTratamento::class)
            ->fillForm([
                'funcionario_id' => $func->id,
                'tipo' => TipoTratamentoPonto::Inclusao->value,
                'data' => '2026-10-19',
                'momento' => '2026-10-18 17:00:00',
                'motivo' => 'Horário em dia diferente do ajustado',
            ])
            ->call('create')
            ->assertNotified('Não foi possível registrar o ajuste');

        $this->assertSame(0, PontoTratamento::count());
    }

    public function test_lista_de_ajustes_respeita_o_escopo_e_a_aprovacao_segue_a_segregacao(): void
    {
        ['user' => $user, 'func' => $meu] = $this->professor();
        ['user' => $rh, 'func' => $rhFunc] = $this->rh();
        $outro = $this->colega();

        $mine = $this->ajuste($meu, $user);
        $deOutro = $this->ajuste($outro, $this->usuario('professor', [])['user']);
        $doRh = $this->ajuste($rhFunc, $rh);

        session(['active_role' => 'professor']);
        Livewire::actingAs($user)
            ->test(ListPontoTratamentos::class)
            ->filterTable('status', null)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$deOutro, $doRh])
            ->assertTableActionHidden('aprovar', $mine);

        session(['active_role' => 'admin']);
        $lista = Livewire::actingAs($rh)->test(ListPontoTratamentos::class)->assertCanSeeTableRecords([$mine, $deOutro, $doRh]);
        $lista->assertTableActionHidden('aprovar', $doRh)->assertTableActionVisible('aprovar', $mine);
        $lista->callTableAction('aprovar', $mine)->assertNotified('Ajuste aprovado');
        $lista->callTableAction('recusar', $deOutro)->assertNotified('Ajuste recusado');

        $this->assertSame(StatusTratamentoPonto::Aprovado, $mine->fresh()->status);
        $this->assertSame(StatusTratamentoPonto::Recusado, $deOutro->fresh()->status);
        $this->assertSame(StatusTratamentoPonto::Pendente, $doRh->fresh()->status);
    }

    private function ajuste(Funcionario $func, User $solicitante): PontoTratamento
    {
        return PontoTratamento::create([
            'unidade_id' => $this->unidade->id,
            'funcionario_id' => $func->id,
            'data' => '2026-10-19',
            'tipo' => TipoTratamentoPonto::Abono,
            'minutos_abono' => 60,
            'motivo' => 'Consulta médica de rotina',
            'status' => StatusTratamentoPonto::Pendente,
            'solicitado_por_user_id' => $solicitante->id,
        ]);
    }

    // ─── Configuração do ponto ───────────────────────────────────────

    public function test_configuracao_so_para_a_administracao(): void
    {
        ['user' => $user] = $this->professor();

        Livewire::actingAs($user)->test(ListPontoConfiguracaos::class)->assertForbidden();
    }

    public function test_admin_configura_nova_unidade_em_controle_interno(): void
    {
        ['user' => $rh] = $this->rh();
        $nova = Unidade::create(['nome' => 'Unidade Norte', 'situacao_funcionamento' => '1']);

        Livewire::actingAs($rh)
            ->test(CreatePontoConfiguracao::class)
            ->fillForm(['unidade_id' => $nova->id, 'modo' => ModoPonto::ControleInterno->value, 'ativo' => true, 'exige_hora_legal' => true, 'tolerancia_diaria_minutos' => 10])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(ModoPonto::ControleInterno, PontoConfiguracao::where('unidade_id', $nova->id)->first()->modo);
    }

    public function test_modo_rep_p_com_pendencias_nao_salva_e_mostra_o_que_falta(): void
    {
        ['user' => $rh] = $this->rh();
        $config = PontoConfiguracao::where('unidade_id', $this->unidade->id)->first();

        Livewire::actingAs($rh)
            ->test(EditPontoConfiguracao::class, ['record' => $config->getRouteKey()])
            ->fillForm(['modo' => ModoPonto::RepP->value])
            ->assertSee('INPI')
            ->assertSee('Atestado Técnico')
            ->assertSee('ICP-Brasil')
            ->call('save')
            ->assertNotified('Configuração não salva');

        $this->assertSame(ModoPonto::ControleInterno, $config->fresh()->modo);
    }

    public function test_acao_de_verificar_integridade_informa_o_resultado(): void
    {
        ['user' => $rh] = $this->rh();
        $this->marcar($this->colega(), '2026-10-19', ['08:00', '12:00']);
        $config = PontoConfiguracao::where('unidade_id', $this->unidade->id)->first();

        Livewire::actingAs($rh)
            ->test(ListPontoConfiguracaos::class)
            ->callTableAction('verificarIntegridade', $config)
            ->assertNotified('Cadeia íntegra');
    }

    public function test_download_do_atestado_exige_permissao_e_arquivo(): void
    {
        Storage::fake('local');
        ['user' => $rh] = $this->rh();
        ['user' => $professor] = $this->professor();
        $config = PontoConfiguracao::where('unidade_id', $this->unidade->id)->first();

        session(['active_role' => 'admin']);
        $this->actingAs($rh)->get(route('ponto.atestado', $config))->assertNotFound();

        Storage::disk('local')->put('ponto/atestados/a.pdf', '%PDF-1.4 teste');
        DB::table('ponto_configuracoes')->where('id', $config->id)->update(['atestado_tecnico_path' => 'ponto/atestados/a.pdf']);

        $this->actingAs($rh)->get(route('ponto.atestado', $config))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        session(['active_role' => 'professor']);
        $this->actingAs($professor)->get(route('ponto.atestado', $config))->assertForbidden();
    }

    // ─── Ficha do funcionário ────────────────────────────────────────

    public function test_admin_cadastra_jornada_e_acordo_na_ficha_do_funcionario(): void
    {
        ['user' => $rh] = $this->rh();
        $func = $this->colega();

        Livewire::actingAs($rh)
            ->test(JornadasPontoRelationManager::class, ['ownerRecord' => $func, 'pageClass' => EditFuncionario::class])
            ->callTableAction('create', data: [
                'vigencia_inicio' => '2026-01-01',
                'minutos_seg' => 480, 'minutos_ter' => 480, 'minutos_qua' => 480, 'minutos_qui' => 480, 'minutos_sex' => 480,
                'minutos_sab' => 0, 'minutos_dom' => 0,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(2400, PontoJornada::where('funcionario_id', $func->id)->first()->minutosSemanais());

        Livewire::actingAs($rh)
            ->test(AcordosBancoHorasRelationManager::class, ['ownerRecord' => $func, 'pageClass' => EditFuncionario::class])
            ->callTableAction('create', data: [
                'tipo' => TipoAcordoBancoHoras::IndividualEscrito->value,
                'prazo_compensacao_meses' => 6,
                'vigencia_inicio' => '2026-01-01',
                'documento_referencia' => 'Termo individual 12/2026',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(1, PontoAcordoBancoHoras::where('funcionario_id', $func->id)->count());
    }

    public function test_acordo_individual_acima_de_seis_meses_e_recusado_no_formulario(): void
    {
        ['user' => $rh] = $this->rh();
        $func = $this->colega();

        Livewire::actingAs($rh)
            ->test(AcordosBancoHorasRelationManager::class, ['ownerRecord' => $func, 'pageClass' => EditFuncionario::class])
            ->callTableAction('create', data: [
                'tipo' => TipoAcordoBancoHoras::IndividualEscrito->value,
                'prazo_compensacao_meses' => 9,
                'vigencia_inicio' => '2026-01-01',
                'documento_referencia' => 'Termo individual 12/2026',
            ])
            ->assertHasTableActionErrors(['prazo_compensacao_meses']);

        $this->assertSame(0, PontoAcordoBancoHoras::count());
    }

    public function test_abas_de_ponto_ficam_ocultas_para_quem_nao_administra(): void
    {
        ['user' => $professor] = $this->professor();
        ['user' => $rh] = $this->rh();
        $func = $this->colega();

        session(['active_role' => 'professor']);
        $this->actingAs($professor);
        $this->assertFalse(JornadasPontoRelationManager::canViewForRecord($func, EditFuncionario::class));
        $this->assertFalse(AcordosBancoHorasRelationManager::canViewForRecord($func, EditFuncionario::class));

        session(['active_role' => 'admin']);
        $this->actingAs($rh);
        $this->assertTrue(JornadasPontoRelationManager::canViewForRecord($func, EditFuncionario::class));
        $this->assertTrue(AcordosBancoHorasRelationManager::canViewForRecord($func, EditFuncionario::class));
    }
}
