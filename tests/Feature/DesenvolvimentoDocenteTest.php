<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatusAcaoDesenvolvimento;
use App\Enums\StatusAutoavaliacaoDocente;
use App\Enums\StatusPlanoDesenvolvimento;
use App\Exceptions\DesenvolvimentoDocenteException;
use App\Models\AcaoDesenvolvimentoDocente;
use App\Models\AutoavaliacaoDocente;
use App\Models\AutoavaliacaoNota;
use App\Models\AvaliacaoDocente;
use App\Models\AvaliacaoDocenteCriterio;
use App\Models\CicloAvaliacaoDocente;
use App\Models\Funcionario;
use App\Models\InstrumentoAvaliacaoDocente;
use App\Models\Pessoa;
use App\Models\PlanoDesenvolvimentoDocente;
use App\Models\User;
use App\Services\AutoavaliacaoDocenteService;
use App\Services\DesenvolvimentoDocenteService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Autoavaliação do professor e plano de desenvolvimento individual (melhoria da Onda 19).
 *
 * "Hoje" é 21/10/2026. O instrumento padrão da fábrica tem dois critérios de peso 1 e escala de 1 a 5.
 */
class DesenvolvimentoDocenteTest extends TestCase
{
    use RefreshDatabase;

    private CicloAvaliacaoDocente $ciclo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-21 09:00:00'));
        $this->ciclo = CicloAvaliacaoDocente::factory()->create([
            'data_inicio' => '2026-08-01',
            'data_fim' => '2026-12-18',
        ]);
    }

    private function auto(): AutoavaliacaoDocenteService
    {
        return app(AutoavaliacaoDocenteService::class);
    }

    private function pdi(): DesenvolvimentoDocenteService
    {
        return app(DesenvolvimentoDocenteService::class);
    }

    /**
     * Usuário com papel e permissões. Com `$comFuncionario`, também o cadastro de funcionário ligado a ele.
     *
     * @param  list<string>  $permissoes
     * @return array{user: User, func: ?Funcionario}
     */
    private function usuario(string $papel, array $permissoes = [], bool $comFuncionario = true): array
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
            $func = Funcionario::factory()->create(['pessoa_id' => $pessoa->id, 'data_admissao' => '2020-02-03']);
        }

        return ['user' => $user, 'func' => $func];
    }

    private function coordenacao(): User
    {
        return $this->usuario('coordenador', ['Update:PlanoDesenvolvimentoDocente', 'Ativar:PlanoDesenvolvimentoDocente', 'Concluir:PlanoDesenvolvimentoDocente', 'AtualizarAcao:PlanoDesenvolvimentoDocente', 'View:PlanoDesenvolvimentoDocente', 'ViewAny:PlanoDesenvolvimentoDocente'], comFuncionario: false)['user'];
    }

    /**
     * Avaliação finalizada do professor, com as notas informadas na ordem dos critérios do instrumento.
     *
     * @param  list<int>  $notas
     */
    private function avaliacaoFinalizada(Funcionario $func, array $notas = [2, 4], ?CicloAvaliacaoDocente $ciclo = null, float $percentual = 60.0): AvaliacaoDocente
    {
        $ciclo ??= $this->ciclo;
        $avaliacao = AvaliacaoDocente::factory()->finalizada($percentual)->create(['ciclo_id' => $ciclo->id, 'funcionario_id' => $func->id]);

        foreach ($ciclo->instrumento->criterios as $i => $criterio) {
            AvaliacaoDocenteCriterio::create(['avaliacao_docente_id' => $avaliacao->id, 'criterio_id' => $criterio->id, 'nota' => $notas[$i] ?? 3]);
        }

        return $avaliacao;
    }

    // ─── Autoavaliação: iniciar ──────────────────────────────────────

    public function test_iniciar_cria_rascunho_com_uma_linha_por_criterio_e_nao_duplica(): void
    {
        ['user' => $prof, 'func' => $func] = $this->usuario('professor');

        $primeira = $this->auto()->iniciar($this->ciclo, $func, $prof);
        $segunda = $this->auto()->iniciar($this->ciclo, $func, $prof);

        $this->assertSame(StatusAutoavaliacaoDocente::Rascunho, $primeira->status);
        $this->assertSame($primeira->id, $segunda->id);
        $this->assertSame(2, $primeira->notas()->count());
        $this->assertSame(1, AutoavaliacaoDocente::count());
    }

    public function test_iniciar_recusa_outro_usuario_ciclo_fechado_funcionario_desligado_e_instrumento_vazio(): void
    {
        ['user' => $prof, 'func' => $func] = $this->usuario('professor');
        ['user' => $colega] = $this->usuario('professor');

        $casos = [
            'outra pessoa' => fn () => $this->auto()->iniciar($this->ciclo, $func, $colega),
            'ciclo encerrado' => fn () => $this->auto()->iniciar(CicloAvaliacaoDocente::factory()->encerrado()->create(['data_inicio' => '2026-08-01', 'data_fim' => '2026-12-18']), $func, $prof),
            'ciclo no passado' => fn () => $this->auto()->iniciar(CicloAvaliacaoDocente::factory()->create(['data_inicio' => '2026-01-01', 'data_fim' => '2026-06-30']), $func, $prof),
            'instrumento sem critérios' => fn () => $this->auto()->iniciar(CicloAvaliacaoDocente::factory()->create(['data_inicio' => '2026-08-01', 'data_fim' => '2026-12-18', 'instrumento_id' => InstrumentoAvaliacaoDocente::factory()->create()->id]), $func, $prof),
        ];

        foreach ($casos as $nome => $caso) {
            try {
                $caso();
                $this->fail("Deveria recusar: {$nome}.");
            } catch (DesenvolvimentoDocenteException) {
                // esperado
            }
        }

        $func->update(['data_desligamento' => '2026-09-30']);
        $this->expectException(DesenvolvimentoDocenteException::class);
        $this->auto()->iniciar($this->ciclo, $func->fresh(), $prof);
    }

    // ─── Autoavaliação: enviar e reabrir ─────────────────────────────

    /**
     * @param  list<int|null>  $notas
     */
    private function preencher(AutoavaliacaoDocente $autoavaliacao, array $notas): void
    {
        foreach ($autoavaliacao->notas as $i => $nota) {
            $nota->update(['nota' => $notas[$i] ?? null]);
        }
    }

    public function test_enviar_exige_todas_as_notas_dentro_da_escala(): void
    {
        ['user' => $prof, 'func' => $func] = $this->usuario('professor');
        $auto = $this->auto()->iniciar($this->ciclo, $func, $prof);

        $this->preencher($auto, [4, null]);
        try {
            $this->auto()->enviar($auto, $prof);
            $this->fail('Faltam notas.');
        } catch (DesenvolvimentoDocenteException $e) {
            $this->assertStringContainsString('Faltam notas', $e->getMessage());
        }

        $this->preencher($auto->fresh(), [4, 6]);
        $this->expectException(DesenvolvimentoDocenteException::class);
        $this->expectExceptionMessage('entre 1 e 5');
        $this->auto()->enviar($auto, $prof);
    }

    public function test_enviar_calcula_a_pontuacao_e_trava_a_autoavaliacao(): void
    {
        ['user' => $prof, 'func' => $func] = $this->usuario('professor');
        $auto = $this->auto()->iniciar($this->ciclo, $func, $prof);
        $this->preencher($auto, [4, 2]); // (4+2)/(5+5) = 60%

        $enviada = $this->auto()->enviar($auto, $prof);

        $this->assertSame(StatusAutoavaliacaoDocente::Enviada, $enviada->status);
        $this->assertSame('60.00', (string) $enviada->pontuacao_percentual);
        $this->assertNotNull($enviada->enviada_em);

        $this->expectException(DesenvolvimentoDocenteException::class);
        $this->expectExceptionMessage('já foi enviada');
        $this->auto()->enviar($enviada, $prof);
    }

    public function test_so_o_dono_envia_a_propria_autoavaliacao(): void
    {
        ['user' => $prof, 'func' => $func] = $this->usuario('professor');
        ['user' => $coord] = $this->usuario('coordenador', [], comFuncionario: false);
        $auto = $this->auto()->iniciar($this->ciclo, $func, $prof);
        $this->preencher($auto, [4, 4]);

        $this->expectException(DesenvolvimentoDocenteException::class);
        $this->auto()->enviar($auto, $coord);
    }

    public function test_reabrir_devolve_ao_professor_e_registra_quem_reabriu(): void
    {
        ['user' => $prof, 'func' => $func] = $this->usuario('professor');
        ['user' => $admin] = $this->usuario('admin', [], comFuncionario: false);
        $auto = $this->auto()->iniciar($this->ciclo, $func, $prof);
        $this->preencher($auto, [4, 4]);
        $this->auto()->enviar($auto, $prof);

        $reaberta = $this->auto()->reabrir($auto, $admin);

        $this->assertSame(StatusAutoavaliacaoDocente::Rascunho, $reaberta->status);
        $this->assertNull($reaberta->pontuacao_percentual);
        $this->assertNull($reaberta->enviada_em);
        $log = Activity::where('log_name', 'autoavaliacao_docente')->where('description', 'Autoavaliação docente reaberta para correção.')->first();
        $this->assertSame($admin->id, $log->causer_id);

        $this->expectException(DesenvolvimentoDocenteException::class);
        $this->auto()->reabrir($reaberta, $admin);
    }

    public function test_comentario_e_observacoes_ficam_cifrados_e_fora_do_log(): void
    {
        ['user' => $prof, 'func' => $func] = $this->usuario('professor');
        $auto = $this->auto()->iniciar($this->ciclo, $func, $prof);
        $auto->update(['comentario' => 'Tenho dificuldade com turmas muito numerosas']);
        $auto->notas()->first()->update(['observacao' => 'Preciso de apoio em gestão de sala']);

        $this->assertStringNotContainsString('numerosas', (string) DB::table('autoavaliacoes_docente')->value('comentario'));
        $this->assertStringNotContainsString('gestão', (string) DB::table('autoavaliacao_notas')->whereNotNull('observacao')->value('observacao'));
        $this->assertSame('Tenho dificuldade com turmas muito numerosas', $auto->fresh()->comentario);
        $this->assertStringNotContainsString('numerosas', Activity::where('log_name', 'autoavaliacao_docente')->get()->toJson());
    }

    // ─── Autoavaliação: escopo e policy ──────────────────────────────

    public function test_professor_ve_so_a_propria_e_a_coordenacao_so_as_enviadas(): void
    {
        ['user' => $ana, 'func' => $funcAna] = $this->usuario('professor');
        ['user' => $beto, 'func' => $funcBeto] = $this->usuario('professor');
        ['user' => $coord] = $this->usuario('coordenador', [], comFuncionario: false);

        $rascunhoAna = AutoavaliacaoDocente::factory()->create(['ciclo_id' => $this->ciclo->id, 'funcionario_id' => $funcAna->id]);
        $enviadaBeto = AutoavaliacaoDocente::factory()->enviada()->create(['ciclo_id' => $this->ciclo->id, 'funcionario_id' => $funcBeto->id]);

        session(['active_role' => 'professor']);
        $this->assertSame([$rascunhoAna->id], AutoavaliacaoDocente::query()->visiveisPara($ana)->pluck('id')->all());
        $this->assertSame([$enviadaBeto->id], AutoavaliacaoDocente::query()->visiveisPara($beto)->pluck('id')->all());

        session(['active_role' => 'coordenador']);
        $this->assertSame([$enviadaBeto->id], AutoavaliacaoDocente::query()->visiveisPara($coord)->pluck('id')->all(), 'O rascunho é privado de quem escreve.');
    }

    public function test_policy_da_autoavaliacao(): void
    {
        $permissoes = ['View:AutoavaliacaoDocente', 'Update:AutoavaliacaoDocente', 'Enviar:AutoavaliacaoDocente'];
        ['user' => $prof, 'func' => $func] = $this->usuario('professor', $permissoes);
        ['user' => $admin] = $this->usuario('admin', [...$permissoes, 'Reabrir:AutoavaliacaoDocente'], comFuncionario: false);
        $rascunho = AutoavaliacaoDocente::factory()->create(['ciclo_id' => $this->ciclo->id, 'funcionario_id' => $func->id]);
        $enviada = AutoavaliacaoDocente::factory()->enviada()->create(['ciclo_id' => CicloAvaliacaoDocente::factory()->create()->id, 'funcionario_id' => $func->id]);

        session(['active_role' => 'professor']);
        $this->assertTrue($prof->can('update', $rascunho));
        $this->assertTrue($prof->can('enviar', $rascunho));
        $this->assertFalse($prof->can('update', $enviada), 'Enviada não se altera.');
        $this->assertFalse($prof->can('reabrir', $enviada));

        session(['active_role' => 'admin']);
        $this->assertFalse($admin->can('view', $rascunho), 'A administração não lê o rascunho.');
        $this->assertTrue($admin->can('view', $enviada));
        $this->assertFalse($admin->can('update', $rascunho), 'Só o dono escreve.');
        $this->assertTrue($admin->can('reabrir', $enviada));
    }

    // ─── Comparativo ─────────────────────────────────────────────────

    public function test_comparativo_cruza_a_autoavaliacao_com_a_media_das_observacoes_finalizadas(): void
    {
        ['func' => $func] = $this->usuario('professor');
        $criterios = $this->ciclo->instrumento->criterios;
        $auto = AutoavaliacaoDocente::factory()->enviada(90)->create(['ciclo_id' => $this->ciclo->id, 'funcionario_id' => $func->id]);
        AutoavaliacaoNota::create(['autoavaliacao_id' => $auto->id, 'criterio_id' => $criterios[0]->id, 'nota' => 5]);
        AutoavaliacaoNota::create(['autoavaliacao_id' => $auto->id, 'criterio_id' => $criterios[1]->id, 'nota' => 4]);
        $this->avaliacaoFinalizada($func, [3, 4], percentual: 70);
        $this->avaliacaoFinalizada($func, [2, 4], percentual: 60);
        AvaliacaoDocente::factory()->create(['ciclo_id' => $this->ciclo->id, 'funcionario_id' => $func->id]); // rascunho não entra

        $comparativo = $this->pdi()->comparativo($func, $this->ciclo);

        $this->assertSame(2, $comparativo['avaliacoes']);
        $this->assertSame(90.0, $comparativo['auto_pontuacao']);
        $this->assertSame(65.0, $comparativo['observada_pontuacao']);
        $this->assertSame(2.5, $comparativo['linhas'][0]['observada']);
        $this->assertSame(2.5, $comparativo['linhas'][0]['diferenca']); // 5 − 2,5: o professor se vê melhor
        $this->assertSame(0.0, $comparativo['linhas'][1]['diferenca']);
    }

    public function test_comparativo_sem_autoavaliacao_enviada_deixa_o_lado_do_professor_vazio(): void
    {
        ['func' => $func] = $this->usuario('professor');
        AutoavaliacaoDocente::factory()->create(['ciclo_id' => $this->ciclo->id, 'funcionario_id' => $func->id]); // rascunho
        $this->avaliacaoFinalizada($func, [3, 4]);

        $comparativo = $this->pdi()->comparativo($func, $this->ciclo);

        $this->assertNull($comparativo['auto_pontuacao']);
        $this->assertNull($comparativo['linhas'][0]['auto']);
        $this->assertNull($comparativo['linhas'][0]['diferenca']);
        $this->assertSame(3.0, $comparativo['linhas'][0]['observada']);
    }

    // ─── Plano: criação ──────────────────────────────────────────────

    public function test_plano_nasce_em_rascunho_com_acao_so_para_os_criterios_fracos(): void
    {
        ['func' => $func] = $this->usuario('professor');
        $coord = $this->coordenacao();
        $avaliacao = $this->avaliacaoFinalizada($func, [2, 4]); // 2/5 = 40% (fraco); 4/5 = 80% (ok)

        $plano = $this->pdi()->criarPlano($avaliacao, $coord);

        $this->assertSame(StatusPlanoDesenvolvimento::Rascunho, $plano->status);
        $this->assertSame($func->id, $plano->funcionario_id);
        $this->assertSame($coord->id, $plano->criado_por_user_id);
        $this->assertCount(1, $plano->acoes);
        $acao = $plano->acoes->first();
        $this->assertSame($this->ciclo->instrumento->criterios[0]->id, $acao->criterio_id);
        $this->assertStringContainsString('nota 2 de 5', $acao->descricao);
        $this->assertSame('2027-01-19', $acao->prazo->toDateString()); // hoje + 90 dias
        $this->assertSame(StatusAcaoDesenvolvimento::Pendente, $acao->status);
    }

    public function test_criterio_no_limite_de_setenta_por_cento_nao_gera_sugestao(): void
    {
        ['func' => $func] = $this->usuario('professor');
        $avaliacao = $this->avaliacaoFinalizada($func, [3, 4]); // 60% gera; 80% não

        $plano = $this->pdi()->criarPlano($avaliacao, $this->coordenacao());

        $this->assertCount(1, $plano->acoes);

        // Escala 10: nota 7 é exatamente 70%, que não gera sugestão.
        $instrumento = InstrumentoAvaliacaoDocente::factory()->comCriterios([['nome' => 'Clima de sala', 'peso' => 1]])->create(['escala_maxima' => 10]);
        $ciclo10 = CicloAvaliacaoDocente::factory()->create(['instrumento_id' => $instrumento->id]);
        $limite = $this->avaliacaoFinalizada($func, [7], $ciclo10);

        $this->assertCount(0, $this->pdi()->criarPlano($limite, $this->coordenacao())->acoes);
    }

    public function test_plano_exige_avaliacao_finalizada_e_nao_se_duplica(): void
    {
        ['func' => $func] = $this->usuario('professor');
        $coord = $this->coordenacao();

        try {
            $this->pdi()->criarPlano(AvaliacaoDocente::factory()->create(['ciclo_id' => $this->ciclo->id, 'funcionario_id' => $func->id]), $coord);
            $this->fail('Avaliação em rascunho não gera plano.');
        } catch (DesenvolvimentoDocenteException $e) {
            $this->assertStringContainsString('finalizada', $e->getMessage());
        }

        $avaliacao = $this->avaliacaoFinalizada($func);
        $this->pdi()->criarPlano($avaliacao, $coord);

        $this->expectException(DesenvolvimentoDocenteException::class);
        $this->expectExceptionMessage('já tem um plano');
        $this->pdi()->criarPlano($avaliacao, $coord);
    }

    // ─── Plano: ativar, ciência, andamento, conclusão ────────────────

    private function planoComAcao(Funcionario $func, array $acao = []): PlanoDesenvolvimentoDocente
    {
        $plano = PlanoDesenvolvimentoDocente::factory()->create([
            'avaliacao_docente_id' => $this->avaliacaoFinalizada($func)->id,
            'funcionario_id' => $func->id,
        ]);
        AcaoDesenvolvimentoDocente::factory()->create(array_merge(['plano_id' => $plano->id, 'prazo' => '2026-12-15'], $acao));

        return $plano;
    }

    public function test_ativar_exige_acao_valida_e_define_a_data_de_revisao(): void
    {
        ['func' => $func] = $this->usuario('professor');
        $coord = $this->coordenacao();

        $vazio = PlanoDesenvolvimentoDocente::factory()->create([
            'avaliacao_docente_id' => $this->avaliacaoFinalizada($func)->id,
            'funcionario_id' => $func->id,
        ]);
        try {
            $this->pdi()->ativar($vazio, $coord);
            $this->fail('Sem ação.');
        } catch (DesenvolvimentoDocenteException $e) {
            $this->assertStringContainsString('ao menos uma ação', $e->getMessage());
        }

        $atrasado = $this->planoComAcao($func, ['prazo' => '2026-09-01']);
        try {
            $this->pdi()->ativar($atrasado, $coord);
            $this->fail('Prazo vencido.');
        } catch (DesenvolvimentoDocenteException $e) {
            $this->assertStringContainsString('prazo já vencido', $e->getMessage());
        }

        $plano = $this->planoComAcao($func);
        $ativo = $this->pdi()->ativar($plano, $coord);

        $this->assertSame(StatusPlanoDesenvolvimento::Ativo, $ativo->status);
        $this->assertNotNull($ativo->ativado_em);
        $this->assertSame('2026-12-15', $ativo->data_revisao->toDateString());

        $this->expectException(DesenvolvimentoDocenteException::class);
        $this->pdi()->ativar($ativo, $coord);
    }

    public function test_ciencia_so_do_dono_com_plano_ativo_e_nao_muda_depois(): void
    {
        ['user' => $prof, 'func' => $func] = $this->usuario('professor');
        ['user' => $outro] = $this->usuario('professor');
        $plano = $this->planoComAcao($func);

        try {
            $this->pdi()->darCiencia($plano, $prof);
            $this->fail('Plano ainda em rascunho.');
        } catch (DesenvolvimentoDocenteException $e) {
            $this->assertStringContainsString('não foi ativado', $e->getMessage());
        }

        $plano = $this->pdi()->ativar($plano, $this->coordenacao());

        try {
            $this->pdi()->darCiencia($plano, $outro);
            $this->fail('Só o dono dá ciência.');
        } catch (DesenvolvimentoDocenteException) {
            // esperado
        }

        $primeira = $this->pdi()->darCiencia($plano, $prof)->ciencia_em;
        $this->travelTo(Carbon::parse('2026-10-25 10:00:00'));
        $segunda = $this->pdi()->darCiencia($plano->fresh(), $prof)->ciencia_em;

        $this->assertNotNull($primeira);
        $this->assertTrue($primeira->equalTo($segunda), 'A primeira ciência é a que vale.');
    }

    public function test_professor_acompanha_as_proprias_acoes_e_conclui_com_resultado(): void
    {
        ['user' => $prof, 'func' => $func] = $this->usuario('professor', ['AtualizarAcao:PlanoDesenvolvimentoDocente']);
        $plano = $this->pdi()->ativar($this->planoComAcao($func), $this->coordenacao());
        session(['active_role' => 'professor']);
        $acao = $plano->acoes->first();

        $emAndamento = $this->pdi()->atualizarAcao($acao, StatusAcaoDesenvolvimento::EmAndamento, null, $prof);
        $this->assertSame(StatusAcaoDesenvolvimento::EmAndamento, $emAndamento->status);

        try {
            $this->pdi()->atualizarAcao($emAndamento, StatusAcaoDesenvolvimento::Concluida, 'curto', $prof);
            $this->fail('Resultado curto.');
        } catch (DesenvolvimentoDocenteException $e) {
            $this->assertStringContainsString('resultado', $e->getMessage());
        }

        $concluida = $this->pdi()->atualizarAcao($emAndamento, StatusAcaoDesenvolvimento::Concluida, 'Apliquei a oficina de metodologias ativas em duas turmas.', $prof);
        $this->assertSame(StatusAcaoDesenvolvimento::Concluida, $concluida->status);
        $this->assertSame('2026-10-21', $concluida->concluida_em->toDateString());
        $this->assertStringContainsString('metodologias ativas', $concluida->resultado);

        $this->expectException(DesenvolvimentoDocenteException::class);
        $this->expectExceptionMessage('já foi encerrada');
        $this->pdi()->atualizarAcao($concluida, StatusAcaoDesenvolvimento::EmAndamento, null, $prof);
    }

    public function test_so_a_coordenacao_cancela_e_nunca_as_acoes_do_proprio_plano(): void
    {
        ['user' => $profCoord, 'func' => $funcDaCoord] = $this->usuario('coordenador', ['Update:PlanoDesenvolvimentoDocente', 'AtualizarAcao:PlanoDesenvolvimentoDocente']);
        ['user' => $prof, 'func' => $func] = $this->usuario('professor', ['AtualizarAcao:PlanoDesenvolvimentoDocente']);
        $coord = $this->coordenacao();

        $planoDoProfessor = $this->pdi()->ativar($this->planoComAcao($func), $coord);
        $planoDaCoordenadora = $this->pdi()->ativar($this->planoComAcao($funcDaCoord), $coord);

        // O professor não cancela nem as próprias.
        session(['active_role' => 'professor']);
        try {
            $this->pdi()->atualizarAcao($planoDoProfessor->acoes->first(), StatusAcaoDesenvolvimento::Cancelada, 'Não vou conseguir fazer esta ação.', $prof);
            $this->fail('Professor não cancela.');
        } catch (DesenvolvimentoDocenteException) {
            // esperado
        }

        // A coordenação cancela a ação de outra pessoa, com justificativa.
        session(['active_role' => 'coordenador']);
        $cancelada = $this->pdi()->atualizarAcao($planoDoProfessor->acoes->first(), StatusAcaoDesenvolvimento::Cancelada, 'Ação substituída por formação interna.', $coord);
        $this->assertSame(StatusAcaoDesenvolvimento::Cancelada, $cancelada->status);

        // Mas a coordenadora não cancela as ações do próprio plano.
        session(['active_role' => 'coordenador']);
        $this->expectException(DesenvolvimentoDocenteException::class);
        $this->pdi()->atualizarAcao($planoDaCoordenadora->acoes->first(), StatusAcaoDesenvolvimento::Cancelada, 'Cancelando a minha própria ação.', $profCoord);
    }

    public function test_andamento_so_em_plano_ativo_e_por_quem_tem_relacao_com_ele(): void
    {
        ['func' => $func] = $this->usuario('professor');
        ['user' => $estranho] = $this->usuario('professor', ['AtualizarAcao:PlanoDesenvolvimentoDocente']);
        $rascunho = $this->planoComAcao($func);
        $ativo = $this->pdi()->ativar($this->planoComAcao($func), $this->coordenacao());

        try {
            $this->pdi()->atualizarAcao($rascunho->acoes->first(), StatusAcaoDesenvolvimento::EmAndamento, null, $estranho);
            $this->fail('Plano em rascunho.');
        } catch (DesenvolvimentoDocenteException $e) {
            $this->assertStringContainsString('plano ativo', $e->getMessage());
        }

        $this->expectException(DesenvolvimentoDocenteException::class);
        $this->pdi()->atualizarAcao($ativo->acoes->first(), StatusAcaoDesenvolvimento::EmAndamento, null, $estranho);
    }

    public function test_concluir_exige_que_nao_haja_acao_em_aberto(): void
    {
        ['user' => $prof, 'func' => $func] = $this->usuario('professor', ['AtualizarAcao:PlanoDesenvolvimentoDocente']);
        $coord = $this->coordenacao();
        $plano = $this->pdi()->ativar($this->planoComAcao($func), $coord);

        try {
            $this->pdi()->concluir($plano, $coord);
            $this->fail('Há ação em aberto.');
        } catch (DesenvolvimentoDocenteException $e) {
            $this->assertStringContainsString('1 ação(ões) em aberto', $e->getMessage());
        }

        session(['active_role' => 'professor']);
        $this->pdi()->atualizarAcao($plano->acoes->first(), StatusAcaoDesenvolvimento::Concluida, 'Participei da formação e apliquei em sala.', $prof);

        $concluido = $this->pdi()->concluir($plano, $coord);
        $this->assertSame(StatusPlanoDesenvolvimento::Concluido, $concluido->status);
        $this->assertNotNull($concluido->concluido_em);
    }

    // ─── Plano: escopo, policy, cifragem ─────────────────────────────

    public function test_professor_ve_so_o_proprio_plano_e_so_depois_de_ativo(): void
    {
        ['user' => $ana, 'func' => $funcAna] = $this->usuario('professor');
        ['func' => $funcBeto] = $this->usuario('professor');
        $coord = $this->coordenacao();

        $rascunhoAna = $this->planoComAcao($funcAna);
        $ativoAna = $this->pdi()->ativar($this->planoComAcao($funcAna), $coord);
        $ativoBeto = $this->pdi()->ativar($this->planoComAcao($funcBeto), $coord);

        session(['active_role' => 'professor']);
        $this->assertSame([$ativoAna->id], PlanoDesenvolvimentoDocente::query()->visiveisPara($ana)->pluck('id')->all());

        session(['active_role' => 'coordenador']);
        $this->assertEqualsCanonicalizing([$rascunhoAna->id, $ativoAna->id, $ativoBeto->id], PlanoDesenvolvimentoDocente::query()->visiveisPara($coord)->pluck('id')->all());
    }

    public function test_policy_do_plano_separa_quem_conduz_de_quem_e_avaliado(): void
    {
        $permissoes = ['View:PlanoDesenvolvimentoDocente', 'Update:PlanoDesenvolvimentoDocente', 'Ativar:PlanoDesenvolvimentoDocente', 'Concluir:PlanoDesenvolvimentoDocente', 'DarCiencia:PlanoDesenvolvimentoDocente', 'AtualizarAcao:PlanoDesenvolvimentoDocente'];
        ['user' => $prof, 'func' => $func] = $this->usuario('professor', ['View:PlanoDesenvolvimentoDocente', 'DarCiencia:PlanoDesenvolvimentoDocente', 'AtualizarAcao:PlanoDesenvolvimentoDocente']);
        ['user' => $coord] = $this->usuario('coordenador', $permissoes, comFuncionario: false);
        $rascunho = $this->planoComAcao($func);
        $ativo = $this->pdi()->ativar($this->planoComAcao($func), $coord);

        session(['active_role' => 'professor']);
        $this->assertFalse($prof->can('view', $rascunho), 'Rascunho é da coordenação.');
        $this->assertTrue($prof->can('view', $ativo));
        $this->assertTrue($prof->can('darCiencia', $ativo));
        $this->assertTrue($prof->can('atualizarAcao', $ativo));
        $this->assertFalse($prof->can('update', $ativo));
        $this->assertFalse($prof->can('ativar', $rascunho));

        session(['active_role' => 'coordenador']);
        $this->assertTrue($coord->can('view', $rascunho));
        $this->assertTrue($coord->can('ativar', $rascunho));
        $this->assertTrue($coord->can('concluir', $ativo));
        $this->assertFalse($coord->can('darCiencia', $ativo), 'Ciência é do professor.');
    }

    public function test_conteudo_do_plano_fica_cifrado_e_fora_do_log(): void
    {
        ['func' => $func] = $this->usuario('professor');
        $plano = $this->planoComAcao($func, ['descricao' => 'Reduzir conflitos com a turma do 9º ano B']);
        $plano->update(['objetivo' => 'Melhorar a relação com os alunos mais velhos']);
        $this->pdi()->ativar($plano, $this->coordenacao());

        $this->assertStringNotContainsString('conflitos', (string) DB::table('acoes_desenvolvimento_docente')->value('descricao'));
        $this->assertStringNotContainsString('relação', (string) DB::table('planos_desenvolvimento_docente')->value('objetivo'));
        $logs = Activity::where('log_name', 'plano_desenvolvimento_docente')->get()->toJson();
        $this->assertStringNotContainsString('conflitos', $logs);
        $this->assertStringNotContainsString('relação', $logs);
    }

    public function test_acao_em_aberto_com_prazo_vencido_esta_atrasada(): void
    {
        ['func' => $func] = $this->usuario('professor');
        $plano = $this->planoComAcao($func);
        $atrasada = AcaoDesenvolvimentoDocente::factory()->create(['plano_id' => $plano->id, 'prazo' => '2026-10-20']);
        $noDia = AcaoDesenvolvimentoDocente::factory()->create(['plano_id' => $plano->id, 'prazo' => '2026-10-21']);
        $concluida = AcaoDesenvolvimentoDocente::factory()->create(['plano_id' => $plano->id, 'prazo' => '2026-09-01', 'status' => StatusAcaoDesenvolvimento::Concluida]);

        $this->assertTrue($atrasada->estaAtrasada());
        $this->assertFalse($noDia->estaAtrasada(), 'O prazo vale até o fim do dia.');
        $this->assertFalse($concluida->estaAtrasada());
    }
}
