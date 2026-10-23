<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SituacaoCargaLetiva;
use App\Filament\Pages\CargaLetiva;
use App\Models\CronogramaAula;
use App\Models\DiaNaoLetivo;
use App\Models\Disciplina;
use App\Models\EtapaEnsinoAgregada;
use App\Models\FrequenciaEscolar;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Turma;
use App\Models\User;
use App\Services\CargaLetivaService;
use App\Services\ReposicaoAulaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Carga letiva e reposição de aulas (Onda 49).
 *
 * "Hoje" é quinta-feira, 22/10/2026. O período vai de 02/02 a 18/12/2026.
 */
class CargaLetivaTest extends TestCase
{
    use RefreshDatabase;

    private PeriodoLetivo $periodo;

    private Turma $turma;

    private Disciplina $disciplina;

    private Pessoa $professor;

    private User $coordenador;

    private User $secretaria;

    private User $professorUsuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-22 09:00:00'));

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['super_admin', 'admin', 'secretaria', 'coordenador', 'professor'] as $papel) {
            Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        }

        foreach (['CargaLetiva:Turma' => ['coordenador', 'secretaria'], 'Reposicao:Turma' => ['coordenador']] as $permissao => $papeis) {
            foreach ($papeis as $papel) {
                Role::findByName($papel)->givePermissionTo(Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'web']));
            }
        }

        $this->coordenador = $this->usuario('coordenador');
        $this->secretaria = $this->usuario('secretaria');
        $this->professorUsuario = $this->usuario('professor');

        $this->periodo = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-02-02', 'data_fim' => '2026-12-18']);
        $this->turma = $this->turma('302');
        $this->disciplina = Disciplina::factory()->create(['nome' => 'Matemática']);
        $this->professor = Pessoa::factory()->create(['nome' => 'Prof. Rui']);

        // Mínimo pequeno para os cenários caberem em poucas aulas.
        config(['frequencia_legal.minimos.fundamental' => ['horas' => 4, 'dias' => 3]]);
    }

    private function usuario(string $papel): User
    {
        $usuario = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $usuario->assignRole($papel);

        return $usuario;
    }

    private function como(User $usuario): User
    {
        session(['active_role' => $usuario->getRoleNames()->first()]);

        return $usuario;
    }

    private function servico(): CargaLetivaService
    {
        return app(CargaLetivaService::class);
    }

    private function turma(?string $codigoEtapa, string $nome = '7º A'): Turma
    {
        $atributos = ['nome' => $nome, 'periodo_letivo_id' => $this->periodo->id];

        if ($codigoEtapa) {
            $atributos['etapa_ensino_agregada_id'] = EtapaEnsinoAgregada::firstOrCreate(['codigo' => $codigoEtapa], ['nome' => "Etapa {$codigoEtapa}"])->id;
        }

        return Turma::factory()->create($atributos);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function aula(string $data, string $inicio = '08:00', string $fim = '08:50', array $extra = [], ?Turma $turma = null): CronogramaAula
    {
        return CronogramaAula::create($extra + [
            'turma_id' => ($turma ?? $this->turma)->id,
            'disciplina_id' => $this->disciplina->id,
            'pessoa_id' => $this->professor->id,
            'data' => $data,
            'hora_inicio' => $inicio,
            'hora_fim' => $fim,
            'conteudo_ministrado' => 'Conteúdo dado',
        ]);
    }

    // --- Horas e dias ------------------------------------------------------------------------------------------------

    public function test_as_horas_somam_a_duracao_real_das_aulas_em_hora_relogio(): void
    {
        foreach (['2026-09-01', '2026-09-02', '2026-09-03', '2026-09-04', '2026-09-08'] as $dia) {
            $this->aula($dia, '08:00', '08:50');
        }

        $apuracao = $this->servico()->apurar($this->turma);

        $this->assertSame(5, $apuracao['aulas_previstas']);
        $this->assertSame(4.2, $apuracao['horas_previstas'], '5 aulas de 50 minutos = 250 minutos = 4,2 horas');
        $this->assertSame(5, $apuracao['dias_previstos']);
        $this->assertSame(4.2, $apuracao['horas_realizadas']);
    }

    public function test_dois_horarios_no_mesmo_dia_contam_um_dia_e_as_duas_aulas(): void
    {
        $this->aula('2026-09-01', '08:00', '08:50');
        $this->aula('2026-09-01', '08:50', '09:40');

        $apuracao = $this->servico()->apurar($this->turma);

        $this->assertSame(1, $apuracao['dias_previstos']);
        $this->assertSame(2, $apuracao['aulas_previstas']);
        $this->assertSame(1.7, $apuracao['horas_previstas']);
    }

    public function test_aula_sem_horario_nao_soma_horas_mas_conta_o_dia(): void
    {
        $this->aula('2026-09-01', '08:00', '08:00');

        $apuracao = $this->servico()->apurar($this->turma);

        $this->assertSame(0.0, $apuracao['horas_previstas']);
        $this->assertSame(1, $apuracao['dias_previstos']);
    }

    public function test_aula_realizada_tem_chamada_ou_conteudo_e_a_passada_sem_nenhum_dos_dois_fica_sem_registro(): void
    {
        $aluno = Matricula::factory()->create(['turma_id' => $this->turma->id, 'situacao' => 'ativa']);

        $comChamada = $this->aula('2026-09-01', extra: ['conteudo_ministrado' => null]);
        FrequenciaEscolar::create(['matricula_id' => $aluno->id, 'cronograma_aula_id' => $comChamada->id, 'situacao' => 'presente']);
        $this->aula('2026-09-02');
        $this->aula('2026-09-03', extra: ['conteudo_ministrado' => '   ']);
        $this->aula('2026-12-01', extra: ['conteudo_ministrado' => null]);

        $apuracao = $this->servico()->apurar($this->turma);

        $this->assertSame(4, $apuracao['aulas_previstas']);
        $this->assertSame(2, $apuracao['aulas_realizadas'], 'a de chamada e a de conteúdo');
        $this->assertSame(1, $apuracao['aulas_sem_registro'], 'passada, sem chamada e sem conteúdo (conteúdo só com espaços não vale)');
        $this->assertSame(2, $apuracao['dias_realizados']);
        $this->assertSame(3, $apuracao['dias_projetados'], 'realizados mais a aula futura');
    }

    public function test_dia_nao_letivo_do_periodo_ou_do_curso_fica_de_fora(): void
    {
        $this->aula('2026-09-01');
        $this->aula('2026-09-02');
        $this->aula('2026-09-03');
        DiaNaoLetivo::create(['periodo_letivo_id' => $this->periodo->id, 'data' => '2026-09-02', 'descricao' => 'Recesso', 'flag_ativo' => true]);
        DiaNaoLetivo::create(['periodo_letivo_id' => $this->periodo->id, 'data' => '2026-09-03', 'descricao' => 'Inativo', 'flag_ativo' => false]);

        $apuracao = $this->servico()->apurar($this->turma);

        $this->assertSame(2, $apuracao['aulas_previstas'], 'o dia 02 é não letivo; o 03 está inativo e vale');
        $this->assertSame(2, $apuracao['dias_previstos']);
    }

    public function test_aulas_fora_do_periodo_nao_entram(): void
    {
        $this->aula('2026-01-15');
        $this->aula('2027-01-15');
        $this->aula('2026-09-01');

        $this->assertSame(1, $this->servico()->apurar($this->turma)['aulas_previstas']);
    }

    // --- Não realizada e reposição -----------------------------------------------------------------------------------

    public function test_aula_nao_realizada_sai_da_conta_e_a_reposicao_entra(): void
    {
        $aula = $this->aula('2026-09-01', extra: ['conteudo_ministrado' => null]);
        $this->aula('2026-09-02');

        $servico = app(ReposicaoAulaService::class);
        $servico->marcarNaoRealizada($aula, 'Professor doente', $this->coordenador);

        $depois = $this->servico()->apurar($this->turma);

        $this->assertSame(1, $depois['aulas_previstas']);
        $this->assertSame(1, $depois['aulas_nao_realizadas']);
        $this->assertSame(0, $depois['aulas_sem_registro']);

        $reposicao = $servico->agendarReposicao($aula, Carbon::parse('2026-11-10'), '08:00', '08:50', null, $this->coordenador);

        $this->assertTrue($reposicao->ehReposicao());
        $this->assertSame($aula->id, $reposicao->reposicao_de_id);
        $this->assertSame($this->professor->id, $reposicao->pessoa_id, 'o professor da aula original');

        $comReposicao = $this->servico()->apurar($this->turma);

        $this->assertSame(2, $comReposicao['aulas_previstas']);
        $this->assertSame(1, $comReposicao['reposicoes']);
        $this->assertSame(1, $comReposicao['aulas_nao_realizadas']);
    }

    public function test_marcar_nao_realizada_exige_motivo_e_recusa_aula_com_chamada(): void
    {
        $aluno = Matricula::factory()->create(['turma_id' => $this->turma->id, 'situacao' => 'ativa']);
        $comChamada = $this->aula('2026-09-01');
        FrequenciaEscolar::create(['matricula_id' => $aluno->id, 'cronograma_aula_id' => $comChamada->id, 'situacao' => 'presente']);
        $semChamada = $this->aula('2026-09-02');
        $servico = app(ReposicaoAulaService::class);

        try {
            $servico->marcarNaoRealizada($comChamada, 'Chuva', $this->coordenador);
            $this->fail('aula com chamada aconteceu');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('chamada lançada', $e->getMessage());
        }

        try {
            $servico->marcarNaoRealizada($semChamada, '  ', $this->coordenador);
            $this->fail('sem motivo');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('motivo', $e->getMessage());
        }

        $servico->marcarNaoRealizada($semChamada, 'Chuva forte', $this->coordenador);

        try {
            $servico->marcarNaoRealizada($semChamada, 'Chuva forte', $this->coordenador);
            $this->fail('já marcada');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('já está marcada', $e->getMessage());
        }

        $log = Activity::query()->where('log_name', 'frequencia')->latest('id')->firstOrFail();
        $this->assertSame('Aula marcada como não realizada.', $log->description);
        $this->assertSame($this->coordenador->id, $log->causer_id);
    }

    public function test_reposicao_so_para_aula_nao_realizada_dentro_das_regras(): void
    {
        $prevista = $this->aula('2026-09-01');
        $naoRealizada = $this->aula('2026-09-10');
        $servico = app(ReposicaoAulaService::class);
        $servico->marcarNaoRealizada($naoRealizada, 'Evento', $this->coordenador);

        $casos = [
            'aula que aconteceu' => [fn () => $servico->agendarReposicao($prevista, Carbon::parse('2026-11-10'), '08:00', '08:50'), 'Só se repõe aula que não aconteceu'],
            'antes da original' => [fn () => $servico->agendarReposicao($naoRealizada, Carbon::parse('2026-09-05'), '08:00', '08:50'), 'antes da aula'],
            'fora do período' => [fn () => $servico->agendarReposicao($naoRealizada, Carbon::parse('2027-02-01'), '08:00', '08:50'), 'dentro do período'],
            'horário invertido' => [fn () => $servico->agendarReposicao($naoRealizada, Carbon::parse('2026-11-10'), '09:00', '08:00'), 'depois do inicial'],
        ];

        foreach ($casos as $nome => [$acao, $trecho]) {
            try {
                $acao();
                $this->fail("deveria recusar: {$nome}");
            } catch (\DomainException $e) {
                $this->assertStringContainsString($trecho, $e->getMessage(), $nome);
            }
        }

        DiaNaoLetivo::create(['periodo_letivo_id' => $this->periodo->id, 'data' => '2026-11-10', 'descricao' => 'Recesso', 'flag_ativo' => true]);

        try {
            $servico->agendarReposicao($naoRealizada, Carbon::parse('2026-11-10'), '08:00', '08:50');
            $this->fail('dia não letivo');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('não letivo', $e->getMessage());
        }

        $servico->agendarReposicao($naoRealizada, Carbon::parse('2026-11-11'), '08:00', '08:50');

        try {
            $servico->agendarReposicao($naoRealizada, Carbon::parse('2026-11-12'), '08:00', '08:50');
            $this->fail('já tem reposição');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('já tem uma reposição', $e->getMessage());
        }
    }

    public function test_reposicao_nao_pode_bater_com_outra_aula_da_turma_ou_do_professor(): void
    {
        $naoRealizada = $this->aula('2026-09-10');
        $this->aula('2026-11-10', '08:00', '09:00');
        $servico = app(ReposicaoAulaService::class);
        $servico->marcarNaoRealizada($naoRealizada, 'Evento', $this->coordenador);

        try {
            $servico->agendarReposicao($naoRealizada, Carbon::parse('2026-11-10'), '08:30', '09:30');
            $this->fail('a turma já tem aula');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('turma já tem aula', $e->getMessage());
        }

        // Aula colada (começa quando a outra termina) não é conflito.
        $colada = $servico->agendarReposicao($naoRealizada, Carbon::parse('2026-11-10'), '09:00', '09:50');
        $this->assertSame('09:00:00', $colada->hora_inicio);

        // Outro professor, mas ocupado em outra turma no mesmo horário.
        $outraTurma = $this->turma('302', '8º B');
        $outroProfessor = Pessoa::factory()->create(['nome' => 'Profa. Lia']);
        $this->aula('2026-11-12', '10:00', '10:50', ['pessoa_id' => $outroProfessor->id], $outraTurma);
        $segunda = $this->aula('2026-09-11');
        $servico->marcarNaoRealizada($segunda, 'Chuva', $this->coordenador);

        try {
            $servico->agendarReposicao($segunda, Carbon::parse('2026-11-12'), '10:00', '10:50', $outroProfessor->id);
            $this->fail('o professor já tem aula');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('professor já tem aula', $e->getMessage());
        }
    }

    public function test_desfazer_so_enquanto_nao_ha_reposicao(): void
    {
        $aula = $this->aula('2026-09-10');
        $servico = app(ReposicaoAulaService::class);
        $servico->marcarNaoRealizada($aula, 'Evento', $this->coordenador);
        $reposicao = $servico->agendarReposicao($aula, Carbon::parse('2026-11-11'), '08:00', '08:50');

        try {
            $servico->desfazerNaoRealizada($aula, $this->coordenador);
            $this->fail('já tem reposição');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('reposição agendada', $e->getMessage());
        }

        // A reposição que também não aconteceu libera a original para ser desfeita.
        $servico->marcarNaoRealizada($reposicao, 'Outro evento', $this->coordenador);
        $this->assertFalse($servico->desfazerNaoRealizada($aula, $this->coordenador)->naoFoiRealizada());
    }

    // --- Situação contra o mínimo ------------------------------------------------------------------------------------

    public function test_calendario_que_nao_fecha_o_minimo_e_insuficiente(): void
    {
        $this->aula('2026-09-01');
        $this->aula('2026-09-02');

        $apuracao = $this->servico()->apurar($this->turma);

        $this->assertSame(SituacaoCargaLetiva::Insuficiente, $apuracao['situacao'], '2 dias e 1,7 hora contra 3 dias e 4 horas');
        $this->assertSame(1, $apuracao['dias_faltantes']);
        $this->assertSame(2.3, $apuracao['horas_faltantes']);
    }

    public function test_fecha_no_papel_mas_a_projecao_cai_abaixo_do_minimo_e_fica_em_risco(): void
    {
        // 5 aulas de 50 min em 5 dias = 4,2 h (mínimo 4 h e 3 dias): fecha. Duas já passaram sem registro: a projeção cai.
        $this->aula('2026-09-01', extra: ['conteudo_ministrado' => null]);
        $this->aula('2026-09-02', extra: ['conteudo_ministrado' => null]);
        $this->aula('2026-09-03');
        $this->aula('2026-12-01');
        $this->aula('2026-12-02');

        $apuracao = $this->servico()->apurar($this->turma);

        $this->assertSame(4.2, $apuracao['horas_previstas']);
        $this->assertSame(2.5, $apuracao['horas_projetadas']);
        $this->assertSame(SituacaoCargaLetiva::EmRisco, $apuracao['situacao']);
    }

    public function test_calendario_que_fecha_e_se_cumpre_esta_em_dia(): void
    {
        foreach (['2026-09-01', '2026-09-02', '2026-09-03', '2026-09-04', '2026-12-01'] as $dia) {
            $this->aula($dia);
        }

        $apuracao = $this->servico()->apurar($this->turma);

        $this->assertSame(SituacaoCargaLetiva::EmDia, $apuracao['situacao']);
        $this->assertSame(0, $apuracao['dias_faltantes']);
        $this->assertSame(0.0, $apuracao['horas_faltantes']);
    }

    public function test_etapa_vem_do_codigo_agregado_e_o_minimo_do_ensino_medio_e_maior(): void
    {
        config(['frequencia_legal.minimos.medio' => ['horas' => 1000, 'dias' => 200]]);
        $medio = $this->turma('304', '1ª série A');
        $eja = $this->turma('399', 'EJA 1');
        $semEtapa = $this->turma(null, 'Sem etapa');

        $this->assertSame('medio', $this->servico()->etapaDaTurma($medio));
        $this->assertSame(['horas' => 1000, 'dias' => 200], $this->servico()->apurar($medio)['minimo']);

        $this->assertNull($this->servico()->etapaDaTurma($eja), 'etapa sem mínimo definido');
        $this->assertSame(SituacaoCargaLetiva::SemMinimo, $this->servico()->apurar($eja)['situacao']);

        $this->assertSame('fundamental', $this->servico()->etapaDaTurma($semEtapa), 'sem etapa informada vale a padrão');
    }

    public function test_modo_calendario_conta_os_dias_uteis_menos_nao_letivos_e_feriados(): void
    {
        config(['frequencia_legal.modo_dias' => 'calendario']);
        $periodo = PeriodoLetivo::create(['nome' => 'Curto', 'data_inicio' => '2026-10-05', 'data_fim' => '2026-10-16']);
        $turma = Turma::factory()->create(['nome' => 'Curta', 'periodo_letivo_id' => $periodo->id]);
        // 05 a 16/10/2026: 10 dias úteis; 12/10 é feriado nacional; 14/10 é recesso da escola.
        DiaNaoLetivo::create(['periodo_letivo_id' => $periodo->id, 'data' => '2026-10-14', 'descricao' => 'Recesso', 'flag_ativo' => true]);
        $this->aula('2026-10-05', extra: [], turma: $turma);

        $apuracao = $this->servico()->apurar($turma);

        $this->assertSame(8, $apuracao['dias_previstos']);
        $this->assertSame(1, $apuracao['dias_realizados']);
        // Realizado: 1 (05/10). Futuros do calendário depois de hoje (22/10): nenhum, o período já acabou.
        $this->assertSame(1, $apuracao['dias_projetados']);
    }

    public function test_apurar_o_periodo_traz_as_turmas_menos_as_canceladas(): void
    {
        $this->turma('302', '6º A');
        Turma::factory()->create(['nome' => 'Cancelada', 'periodo_letivo_id' => $this->periodo->id, 'status' => 'cancelada']);

        $nomes = $this->servico()->apurarPeriodo($this->periodo)->map(fn (array $a): string => $a['turma']->nome)->all();

        $this->assertContains('6º A', $nomes);
        $this->assertContains('7º A', $nomes);
        $this->assertNotContains('Cancelada', $nomes);
    }

    public function test_para_o_historico_devolve_o_cronograma_ou_nada(): void
    {
        $this->assertNull($this->servico()->paraHistorico($this->turma), 'sem cronograma, o histórico segue com o padrão antigo');

        foreach (['2026-09-01', '2026-09-02', '2026-12-01'] as $dia) {
            $this->aula($dia, '08:00', '10:00');
        }

        $this->assertSame(['dias' => 3, 'horas' => 6], $this->servico()->paraHistorico($this->turma));
    }

    public function test_aulas_sem_registro_so_as_passadas_sem_chamada_nem_conteudo(): void
    {
        $this->aula('2026-09-01', extra: ['conteudo_ministrado' => null]);
        $this->aula('2026-09-02');
        $this->aula('2026-12-01', extra: ['conteudo_ministrado' => null]);

        $datas = $this->servico()->aulasSemRegistro($this->turma)->map(fn (CronogramaAula $a): string => $a->data->toDateString())->all();

        $this->assertSame(['2026-09-01'], $datas);
    }

    // --- Telas -------------------------------------------------------------------------------------------------------

    public function test_pagina_mostra_as_turmas_o_semaforo_e_as_aulas_sem_registro(): void
    {
        $this->aula('2026-09-01', extra: ['conteudo_ministrado' => null]);
        $this->aula('2026-09-02');

        Livewire::actingAs($this->como($this->coordenador))
            ->test(CargaLetiva::class)
            ->set('periodoId', $this->periodo->id)
            ->set('turmaId', $this->turma->id)
            ->assertSuccessful()
            ->assertSee('7º A')
            ->assertSee('Calendário insuficiente')
            ->assertSee('01/09/2026')
            ->assertSee('Matemática');
    }

    public function test_marcar_nao_realizada_e_agendar_reposicao_pela_pagina(): void
    {
        $aula = $this->aula('2026-09-01', extra: ['conteudo_ministrado' => null]);

        Livewire::actingAs($this->como($this->coordenador))
            ->test(CargaLetiva::class)
            ->set('periodoId', $this->periodo->id)
            ->set('turmaId', $this->turma->id)
            ->callAction('naoRealizada', data: ['motivo' => 'Greve de ônibus'], arguments: ['aula' => $aula->id])
            ->assertNotified('Aula marcada como não realizada')
            ->callAction('repor', data: ['data' => '2026-11-10', 'hora_inicio' => '08:00', 'hora_fim' => '08:50'], arguments: ['aula' => $aula->id])
            ->assertNotified('Reposição agendada');

        $this->assertTrue($aula->refresh()->naoFoiRealizada());
        $this->assertSame('Greve de ônibus', $aula->motivo_nao_realizacao);
        $this->assertSame(1, CronogramaAula::query()->where('reposicao_de_id', $aula->id)->count());
    }

    public function test_a_aula_vem_do_cliente_e_so_vale_se_for_da_turma_da_tela(): void
    {
        $outraTurma = $this->turma('302', '9º C');
        $alheia = $this->aula('2026-09-01', extra: ['conteudo_ministrado' => null], turma: $outraTurma);

        Livewire::actingAs($this->como($this->coordenador))
            ->test(CargaLetiva::class)
            ->set('periodoId', $this->periodo->id)
            ->set('turmaId', $this->turma->id)
            ->callAction('naoRealizada', data: ['motivo' => 'Teste'], arguments: ['aula' => $alheia->id])
            ->assertNotified('Aula não encontrada nesta turma');

        $this->assertFalse($alheia->refresh()->naoFoiRealizada());
    }

    public function test_secretaria_ve_a_pagina_mas_nao_marca_nem_repoe(): void
    {
        $aula = $this->aula('2026-09-01', extra: ['conteudo_ministrado' => null]);

        $tela = Livewire::actingAs($this->como($this->secretaria))
            ->test(CargaLetiva::class)
            ->set('periodoId', $this->periodo->id)
            ->set('turmaId', $this->turma->id)
            ->assertSuccessful()
            ->assertSee('01/09/2026');

        $this->assertFalse($tela->instance()->podeRepor());
        $this->assertFalse($aula->refresh()->naoFoiRealizada());
    }

    public function test_professor_nao_abre_a_pagina_nem_os_relatorios(): void
    {
        $this->actingAs($this->como($this->professorUsuario))->get('/admin/calendario/carga-letiva')->assertForbidden();
        $this->actingAs($this->como($this->professorUsuario))->get(route('carga-letiva.pdf', $this->periodo))->assertForbidden();
        $this->actingAs($this->como($this->professorUsuario))->get(route('carga-letiva.csv', $this->periodo))->assertForbidden();
    }

    public function test_relatorio_em_pdf_e_em_planilha(): void
    {
        foreach (['2026-09-01', '2026-09-02', '2026-09-03', '2026-09-04', '2026-12-01'] as $dia) {
            $this->aula($dia);
        }

        $pdf = $this->actingAs($this->como($this->coordenador))->get(route('carga-letiva.pdf', $this->periodo));
        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('content-type'));

        $csv = $this->actingAs($this->como($this->coordenador))->get(route('carga-letiva.csv', $this->periodo));
        $csv->assertOk();
        $conteudo = $csv->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $conteudo, 'BOM para o Excel');
        $this->assertStringContainsString('Turma;Etapa;"Dias previstos"', $conteudo);
        $this->assertStringContainsString('"7º A";fundamental;5;', $conteudo);
        $this->assertStringContainsString('Em dia', $conteudo);
    }
}
