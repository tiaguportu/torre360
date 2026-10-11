<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CanalReguaFollowUp;
use App\Enums\GatilhoReguaFollowUp;
use App\Enums\SituacaoMatricula;
use App\Models\Interessado;
use App\Models\Matricula;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\ReguaFollowUp;
use App\Models\ReguaFollowUpLog;
use App\Models\StatusInteressado;
use App\Models\Turma;
use App\Models\User;
use App\Services\ReguaFollowUpService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

class ReguaRiscoEvasaoResponsavelLoteF2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Desativa regras padrão seedadas para isolar os testes do gatilho RiscoEvasaoRematricula
        ReguaFollowUp::query()->update(['is_ativo' => false]);
    }

    private function criarEstruturaBase(): array
    {
        $consultor = User::factory()->create(['name' => 'Consultor Teste', 'activated_at' => now()]);
        $status = StatusInteressado::factory()->create(['nome' => 'Em Atendimento', 'is_final' => false]);
        $origem = OrigemInteressado::firstOrCreate(['nome' => 'Site']);

        return [$consultor, $status, $origem];
    }

    public function test_aluno_diferente_de_responsavel_gera_um_candidato(): void
    {
        Carbon::setTestNow('2026-10-15 10:00:00');
        [$consultor, $status, $origem] = $this->criarEstruturaBase();

        // Aluno e Responsável são pessoas distintas
        $aluno = Pessoa::factory()->create(['nome' => 'Enzo Gabriel Aluno']);
        $responsavel = Pessoa::factory()->create([
            'nome' => 'Mariana Responsavel',
            'email' => 'mariana.mae@example.com',
        ]);

        // Vincula Aluno -> Responsável na tabela aluno_responsavel
        $aluno->responsaveis()->attach($responsavel->id);

        // Matrícula ativa do aluno com risco alto
        $turma = Turma::factory()->create();
        Matricula::factory()->create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'situacao' => SituacaoMatricula::ATIVA,
            'risco_evasao_score' => 85,
        ]);

        // Lead cadastrado em nome da mãe (responsável)
        $lead = Interessado::create([
            'pessoa_id' => $responsavel->id,
            'status_interessado_id' => $status->id,
            'origem_interessado_id' => $origem->id,
            'usuario_id' => $consultor->id,
        ]);

        config(['risco_evasao.playbook.corte_risco' => 60]);

        $regra = ReguaFollowUp::create([
            'nome' => 'Acolhimento Aluno em Risco',
            'gatilho' => GatilhoReguaFollowUp::RiscoEvasaoRematricula,
            'dias_offset' => 0,
            'canal' => CanalReguaFollowUp::NotificacaoSistema,
            'assunto' => 'Alerta de Acompanhamento: {{NOME_ALUNO}}',
            'mensagem' => 'Acompanhar família de {{NOME_ALUNO}} no Torre360.',
            'is_ativo' => true,
        ]);

        $service = app(ReguaFollowUpService::class);
        $resultado = $service->processarReguaDiaria(now(), dryRun: false);

        $this->assertEquals(1, $resultado['total_notificacoes_enviadas']);
        $this->assertEquals(1, DatabaseNotification::count());
    }

    public function test_dois_irmaos_em_risco_geram_apenas_um_candidato_deduplicado(): void
    {
        Carbon::setTestNow('2026-10-15 10:00:00');
        [$consultor, $status, $origem] = $this->criarEstruturaBase();

        $mae = Pessoa::factory()->create(['nome' => 'Patrícia Mãe', 'email' => 'patricia@example.com']);
        $irmao1 = Pessoa::factory()->create(['nome' => 'Lucas Irmão']);
        $irmao2 = Pessoa::factory()->create(['nome' => 'Mateus Irmão']);

        $irmao1->responsaveis()->attach($mae->id);
        $irmao2->responsaveis()->attach($mae->id);

        $turma = Turma::factory()->create();
        Matricula::factory()->create([
            'pessoa_id' => $irmao1->id,
            'turma_id' => $turma->id,
            'situacao' => SituacaoMatricula::ATIVA,
            'risco_evasao_score' => 75,
        ]);
        Matricula::factory()->create([
            'pessoa_id' => $irmao2->id,
            'turma_id' => $turma->id,
            'situacao' => SituacaoMatricula::ATIVA,
            'risco_evasao_score' => 90,
        ]);

        $lead = Interessado::create([
            'pessoa_id' => $mae->id,
            'status_interessado_id' => $status->id,
            'origem_interessado_id' => $origem->id,
            'usuario_id' => $consultor->id,
        ]);

        config(['risco_evasao.playbook.corte_risco' => 60]);

        ReguaFollowUp::create([
            'nome' => 'Acolhimento Família',
            'gatilho' => GatilhoReguaFollowUp::RiscoEvasaoRematricula,
            'dias_offset' => 0,
            'canal' => CanalReguaFollowUp::NotificacaoSistema,
            'assunto' => 'Acompanhamento Escolar',
            'mensagem' => 'Contatar família.',
            'is_ativo' => true,
        ]);

        $service = app(ReguaFollowUpService::class);
        $resultado = $service->processarReguaDiaria(now(), dryRun: false);

        $this->assertEquals(1, $resultado['total_notificacoes_enviadas']);
    }

    public function test_matricula_inativa_gera_zero_candidatos(): void
    {
        Carbon::setTestNow('2026-10-15 10:00:00');
        [$consultor, $status, $origem] = $this->criarEstruturaBase();

        $aluno = Pessoa::factory()->create(['nome' => 'Aluno Inativo']);
        $responsavel = Pessoa::factory()->create(['nome' => 'Pai']);
        $aluno->responsaveis()->attach($responsavel->id);

        $turma = Turma::factory()->create();
        Matricula::factory()->create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'situacao' => SituacaoMatricula::CANCELADA, // Inativa
            'risco_evasao_score' => 95,
        ]);

        Interessado::create([
            'pessoa_id' => $responsavel->id,
            'status_interessado_id' => $status->id,
            'origem_interessado_id' => $origem->id,
            'usuario_id' => $consultor->id,
        ]);

        config(['risco_evasao.playbook.corte_risco' => 60]);

        ReguaFollowUp::create([
            'nome' => 'Regra Evasão',
            'gatilho' => GatilhoReguaFollowUp::RiscoEvasaoRematricula,
            'dias_offset' => 0,
            'canal' => CanalReguaFollowUp::NotificacaoSistema,
            'assunto' => 'Acompanhamento',
            'mensagem' => 'Mensagem teste',
            'is_ativo' => true,
        ]);

        $service = app(ReguaFollowUpService::class);
        $resultado = $service->processarReguaDiaria(now(), dryRun: false);

        $this->assertEquals(0, $resultado['total_notificacoes_enviadas']);
    }

    public function test_reexecucao_no_mesmo_dia_nao_duplica_envio(): void
    {
        Carbon::setTestNow('2026-10-15 10:00:00');
        [$consultor, $status, $origem] = $this->criarEstruturaBase();

        $aluno = Pessoa::factory()->create(['nome' => 'Aluno Idempotente']);
        $responsavel = Pessoa::factory()->create(['nome' => 'Responsavel Idempotente']);
        $aluno->responsaveis()->attach($responsavel->id);

        $turma = Turma::factory()->create();
        Matricula::factory()->create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'situacao' => SituacaoMatricula::ATIVA,
            'risco_evasao_score' => 80,
        ]);

        Interessado::create([
            'pessoa_id' => $responsavel->id,
            'status_interessado_id' => $status->id,
            'origem_interessado_id' => $origem->id,
            'usuario_id' => $consultor->id,
        ]);

        config(['risco_evasao.playbook.corte_risco' => 60]);

        ReguaFollowUp::create([
            'nome' => 'Regra Idempotente',
            'gatilho' => GatilhoReguaFollowUp::RiscoEvasaoRematricula,
            'dias_offset' => 0,
            'canal' => CanalReguaFollowUp::NotificacaoSistema,
            'assunto' => 'Acompanhamento',
            'mensagem' => 'Mensagem teste',
            'is_ativo' => true,
        ]);

        $service = app(ReguaFollowUpService::class);
        $primeira = $service->processarReguaDiaria(now(), dryRun: false);
        $this->assertEquals(1, $primeira['total_notificacoes_enviadas']);

        // Segunda execução imediata no mesmo dia
        $segunda = $service->processarReguaDiaria(now(), dryRun: false);
        $this->assertEquals(0, $segunda['total_notificacoes_enviadas']);
        $this->assertEquals(1, DatabaseNotification::count());
    }

    public function test_corpo_enviado_a_familia_nao_contem_o_score_nem_a_palavra_evasao(): void
    {
        Carbon::setTestNow('2026-10-15 10:00:00');
        [$consultor, $status, $origem] = $this->criarEstruturaBase();

        $aluno = Pessoa::factory()->create(['nome' => 'Sofia Aluna']);
        $responsavel = Pessoa::factory()->create(['nome' => 'Fernanda Mae', 'email' => 'fernanda@example.com']);
        $aluno->responsaveis()->attach($responsavel->id);

        $turma = Turma::factory()->create();
        Matricula::factory()->create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'situacao' => SituacaoMatricula::ATIVA,
            'risco_evasao_score' => 88,
        ]);

        $lead = Interessado::create([
            'pessoa_id' => $responsavel->id,
            'status_interessado_id' => $status->id,
            'origem_interessado_id' => $origem->id,
            'usuario_id' => $consultor->id,
        ]);

        config(['risco_evasao.playbook.corte_risco' => 60]);

        $regra = ReguaFollowUp::create([
            'nome' => 'Contato Proativo Pedagógico',
            'gatilho' => GatilhoReguaFollowUp::RiscoEvasaoRematricula,
            'dias_offset' => 0,
            'canal' => CanalReguaFollowUp::NotificacaoSistema,
            'assunto' => 'Como podemos apoiar {{NOME_ALUNO}}?',
            'mensagem' => 'Olá {{PRIMEIRO_NOME}}, queremos saber como estão os estudos de {{NOME_ALUNO}}.',
            'is_ativo' => true,
        ]);

        $service = app(ReguaFollowUpService::class);
        $service->processarReguaDiaria(now(), dryRun: false);

        $log = ReguaFollowUpLog::latest('id')->first();
        $this->assertNotNull($log);

        $assunto = (string) $log->assunto_enviado;
        $mensagem = (string) $log->mensagem_enviada;

        // O nome do aluno em risco deve estar presente
        $this->assertStringContainsString('Sofia Aluna', $assunto);
        $this->assertStringContainsString('Sofia Aluna', $mensagem);

        // O score NUNCA deve estar no conteúdo enviado à família
        $this->assertStringNotContainsString('88', $assunto);
        $this->assertStringNotContainsString('88', $mensagem);
        $this->assertStringNotContainsString('%', $assunto);
        $this->assertStringNotContainsString('%', $mensagem);

        // A palavra "evasão" nunca deve constar
        $this->assertStringNotContainsStringIgnoringCase('evasão', $assunto);
        $this->assertStringNotContainsStringIgnoringCase('evasao', $assunto);
        $this->assertStringNotContainsStringIgnoringCase('evasão', $mensagem);
        $this->assertStringNotContainsStringIgnoringCase('evasao', $mensagem);
    }
}
