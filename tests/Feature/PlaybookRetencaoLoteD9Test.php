<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CanalReguaFollowUp;
use App\Enums\GatilhoReguaFollowUp;
use App\Enums\StatusRematricula;
use App\Enums\StatusRetencaoTarefa;
use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\Interessado;
use App\Models\Matricula;
use App\Models\OrigemInteressado;
use App\Models\PeriodoLetivo;
use App\Models\PeriodoRematricula;
use App\Models\Pessoa;
use App\Models\ReguaFollowUp;
use App\Models\Rematricula;
use App\Models\RetencaoTarefa;
use App\Models\RiscoEvasaoConfiguracao;
use App\Models\StatusInteressado;
use App\Models\User;
use App\Policies\RetencaoTarefaPolicy;
use App\Services\ReguaFollowUpService;
use App\Services\RiscoEvasaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlaybookRetencaoLoteD9Test extends TestCase
{
    use RefreshDatabase;

    private function criarMatriculaComTurma(?int $pessoaId = null): Matricula
    {
        return $pessoaId
            ? Matricula::factory()->create(['pessoa_id' => $pessoaId])
            : Matricula::factory()->create();
    }

    public function test_risco_evasao_abaixo_do_corte_nao_dispara_playbook(): void
    {
        $matricula = $this->criarMatriculaComTurma();

        // Aluno sem faltas, sem reprovação e sem faturas vencidas -> score 0
        $score = RiscoEvasaoService::recalcular($matricula);

        $this->assertEquals(0, $score);
        $this->assertDatabaseCount('retencao_tarefas', 0);
        $this->assertNull($matricula->fresh()->risco_evasao_alerta_em);
    }

    public function test_risco_evasao_acima_do_corte_cria_tarefa_e_alerta_coordenacao(): void
    {
        Role::firstOrCreate(['name' => 'coordenador', 'guard_name' => 'web']);
        $coordenador = User::factory()->create(['activated_at' => now()]);
        $coordenador->assignRole('coordenador');

        $matricula = $this->criarMatriculaComTurma();

        // Força score alto configurando o corte baixo no teste para disparar o playbook
        config(['risco_evasao.playbook.corte_risco' => 25]);

        // Cria débito vencido para dar 25 pontos de inadimplência
        $contrato = Contrato::create(['matricula_id' => $matricula->id, 'valor_total' => 1000]);
        Fatura::create([
            'contrato_id' => $contrato->id,
            'status' => 'atrasado',
            'vencimento' => now()->subDays(10)->toDateString(),
        ]);

        $score = RiscoEvasaoService::recalcular($matricula);

        $this->assertGreaterThanOrEqual(25, $score);
        $this->assertDatabaseHas('retencao_tarefas', [
            'matricula_id' => $matricula->id,
            'aluno_pessoa_id' => $matricula->pessoa_id,
            'status' => StatusRetencaoTarefa::Pendente->value,
        ]);

        $this->assertNotNull($matricula->fresh()->risco_evasao_alerta_em);

        // Notificação interna gravada no banco para os coordenadores
        $this->assertGreaterThanOrEqual(1, DatabaseNotification::count());
    }

    public function test_playbook_nao_repete_alerta_dentro_do_intervalo_configuravel(): void
    {
        $matricula = $this->criarMatriculaComTurma();
        config([
            'risco_evasao.playbook.corte_risco' => 20,
            'risco_evasao.playbook.intervalo_dias_alerta' => 15,
        ]);

        $contrato = Contrato::create(['matricula_id' => $matricula->id, 'valor_total' => 1000]);
        Fatura::create([
            'contrato_id' => $contrato->id,
            'status' => 'atrasado',
            'vencimento' => now()->subDays(5)->toDateString(),
        ]);

        // Primeira execução: cria a primeira tarefa
        RiscoEvasaoService::recalcular($matricula);
        $this->assertDatabaseCount('retencao_tarefas', 1);

        // Segunda execução imediata: cooldown bloqueia repetição
        RiscoEvasaoService::recalcular($matricula);
        $this->assertDatabaseCount('retencao_tarefas', 1);

        // Segunda execução com data simulada 5 dias depois (ainda dentro dos 15 dias)
        $this->travel(5)->days();
        RiscoEvasaoService::recalcular($matricula);
        $this->assertDatabaseCount('retencao_tarefas', 1);

        // Terceira execução com data simulada 20 dias depois (passou da janela de 15 dias)
        $this->travel(20)->days();
        RiscoEvasaoService::recalcular($matricula);
        $this->assertDatabaseCount('retencao_tarefas', 2);
    }

    public function test_tarefa_de_retencao_conecta_com_fluxo_de_rematricula(): void
    {
        $matricula = $this->criarMatriculaComTurma();
        $origem = PeriodoLetivo::factory()->create();
        $destino = PeriodoLetivo::factory()->create();
        $campanha = PeriodoRematricula::create([
            'nome' => 'Rematrícula 2027',
            'periodo_letivo_origem_id' => $origem->id,
            'periodo_letivo_destino_id' => $destino->id,
            'data_inicio' => now()->subDays(5)->toDateString(),
            'data_fim' => now()->addDays(20)->toDateString(),
            'is_ativo' => true,
        ]);

        $solicitante = User::factory()->create(['activated_at' => now()]);
        $rematricula = Rematricula::create([
            'periodo_rematricula_id' => $campanha->id,
            'matricula_origem_id' => $matricula->id,
            'solicitante_user_id' => $solicitante->id,
            'status' => StatusRematricula::Iniciada,
        ]);

        config(['risco_evasao.playbook.corte_risco' => 25]);

        $contrato = Contrato::create(['matricula_id' => $matricula->id, 'valor_total' => 1000]);
        Fatura::create([
            'contrato_id' => $contrato->id,
            'status' => 'atrasado',
            'vencimento' => now()->subDays(5)->toDateString(),
        ]);

        RiscoEvasaoService::recalcular($matricula);

        $tarefa = RetencaoTarefa::where('matricula_id', $matricula->id)->first();
        $this->assertNotNull($tarefa);
        $this->assertEquals($rematricula->id, $tarefa->rematricula_id);
        $this->assertStringContainsString('Rematrícula 2027', (string) $tarefa->descricao);
    }

    public function test_gatilho_regua_follow_up_risco_evasao_rematricula_identifica_alunos(): void
    {
        $consultor = User::factory()->create(['activated_at' => now()]);
        $pessoa = Pessoa::factory()->create(['email' => 'familia@example.com']);
        $matricula = $this->criarMatriculaComTurma($pessoa->id);

        $status = StatusInteressado::factory()->create(['nome' => 'Novo Lead']);
        $origem = OrigemInteressado::firstOrCreate(['nome' => 'Site']);
        $lead = Interessado::create([
            'pessoa_id' => $pessoa->id,
            'status_interessado_id' => $status->id,
            'origem_interessado_id' => $origem->id,
            'usuario_id' => $consultor->id,
        ]);

        // Força score 70 na matrícula
        $matricula->update(['risco_evasao_score' => 70]);

        config(['risco_evasao.playbook.corte_risco' => 60]);

        $regra = ReguaFollowUp::create([
            'nome' => 'Acolhimento Aluno em Risco',
            'gatilho' => GatilhoReguaFollowUp::RiscoEvasaoRematricula,
            'dias_offset' => 0,
            'canal' => CanalReguaFollowUp::NotificacaoSistema,
            'assunto' => 'Alerta de Retenção: [Nome do Aluno]',
            'mensagem' => 'Acompanhar família de [Nome do Aluno] no Torre360.',
            'is_ativo' => true,
        ]);

        $service = app(ReguaFollowUpService::class);
        $resultado = $service->processarReguaDiaria(now(), dryRun: false);

        $this->assertGreaterThanOrEqual(1, $resultado['total_notificacoes_enviadas']);
        $this->assertGreaterThanOrEqual(1, DatabaseNotification::count());
    }

    public function test_retencao_tarefa_policy_respeita_permissoes_shield(): void
    {
        $policy = new RetencaoTarefaPolicy;
        $userSemPermissao = User::factory()->create(['activated_at' => now()]);

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $userSuperAdmin = User::factory()->create(['activated_at' => now()]);
        $userSuperAdmin->assignRole('super_admin');

        $matricula = $this->criarMatriculaComTurma();
        $tarefa = RetencaoTarefa::create([
            'matricula_id' => $matricula->id,
            'aluno_pessoa_id' => $matricula->pessoa_id,
            'score_risco' => 75,
            'titulo' => 'Acompanhamento Teste',
        ]);

        $this->assertFalse($policy->viewAny($userSemPermissao));
        $this->assertFalse($policy->update($userSemPermissao, $tarefa));

        $this->assertTrue($policy->viewAny($userSuperAdmin));
        $this->assertTrue($policy->update($userSuperAdmin, $tarefa));
    }

    public function test_configuracao_playbook_sobrescreve_parametros_dinamicamente(): void
    {
        $usuario = User::factory()->create(['activated_at' => now()]);

        RiscoEvasaoConfiguracao::create([
            'valores' => [
                'pesos' => config('risco_evasao.pesos'),
                'faixas_cor' => config('risco_evasao.faixas_cor'),
                'frequencia' => config('risco_evasao.frequencia'),
                'desempenho' => config('risco_evasao.desempenho'),
                'playbook' => [
                    'corte_risco' => 45,
                    'intervalo_dias_alerta' => 7,
                    'notificar_coordenacao' => false,
                    'criar_tarefa' => true,
                ],
            ],
            'atualizado_por' => $usuario->id,
        ]);

        RiscoEvasaoConfiguracao::limparCache();
        RiscoEvasaoConfiguracao::aplicar();

        $this->assertEquals(45, config('risco_evasao.playbook.corte_risco'));
        $this->assertEquals(7, config('risco_evasao.playbook.intervalo_dias_alerta'));
        $this->assertFalse(config('risco_evasao.playbook.notificar_coordenacao'));
    }
}
