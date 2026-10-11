<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CanalReguaFollowUp;
use App\Enums\GatilhoReguaFollowUp;
use App\Enums\SituacaoMatricula;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\Matricula;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\ReguaFollowUp;
use App\Models\StatusInteressado;
use App\Models\Turma;
use App\Models\User;
use App\Services\ReguaFollowUpService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReguaRiscoEvasaoLoteG4Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ReguaFollowUp::query()->update(['is_ativo' => false]);
    }

    private function criarEstruturaBase(bool $isFinal = false, bool $isGanho = false): array
    {
        $consultor = User::factory()->create(['name' => 'Consultor G4', 'activated_at' => now()]);
        $status = StatusInteressado::factory()->create([
            'nome' => $isFinal ? ($isGanho ? 'Matriculado' : 'Perdido') : 'Em Atendimento',
            'is_final' => $isFinal,
            'is_ganho' => $isGanho,
        ]);
        $origem = OrigemInteressado::firstOrCreate(['nome' => 'Site']);

        return [$consultor, $status, $origem];
    }

    public function test_lead_em_etapa_matriculado_gera_um_candidato(): void
    {
        Carbon::setTestNow('2026-10-15 10:00:00');
        [$consultor, $statusMatriculado, $origem] = $this->criarEstruturaBase(isFinal: true, isGanho: true);

        $aluno = Pessoa::factory()->create(['nome' => 'Bruno Aluno']);
        $responsavel = Pessoa::factory()->create(['nome' => 'Ana Mae', 'email' => 'ana.mae@example.com']);
        $aluno->responsaveis()->attach($responsavel->id);

        $turma = Turma::factory()->create();
        Matricula::factory()->create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'situacao' => SituacaoMatricula::ATIVA,
            'risco_evasao_score' => 85,
        ]);

        $lead = Interessado::create([
            'pessoa_id' => $responsavel->id,
            'status_interessado_id' => $statusMatriculado->id,
            'origem_interessado_id' => $origem->id,
            'usuario_id' => $consultor->id,
        ]);

        config(['risco_evasao.playbook.corte_risco' => 60]);

        $regra = ReguaFollowUp::create([
            'nome' => 'Regra Aluno Matriculado em Risco',
            'gatilho' => GatilhoReguaFollowUp::RiscoEvasaoRematricula,
            'dias_offset' => 0,
            'canal' => CanalReguaFollowUp::NotificacaoSistema,
            'assunto' => 'Apoio a {{NOME_ALUNO}}',
            'mensagem' => 'Acompanhar família de {{NOME_ALUNO}}.',
            'is_ativo' => true,
        ]);

        $service = app(ReguaFollowUpService::class);
        $resultado = $service->processarReguaDiaria(now(), dryRun: false);

        $this->assertEquals(1, $resultado['total_notificacoes_enviadas']);
    }

    public function test_lead_perdido_que_e_responsavel_de_aluno_em_risco_gera_um_candidato(): void
    {
        Carbon::setTestNow('2026-10-15 10:00:00');
        [$consultor, $statusPerdido, $origem] = $this->criarEstruturaBase(isFinal: true, isGanho: false);

        $aluno = Pessoa::factory()->create(['nome' => 'Caio Aluno']);
        $responsavel = Pessoa::factory()->create(['nome' => 'Marcos Pai', 'email' => 'marcos.pai@example.com']);
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
            'status_interessado_id' => $statusPerdido->id,
            'origem_interessado_id' => $origem->id,
            'usuario_id' => $consultor->id,
        ]);

        config(['risco_evasao.playbook.corte_risco' => 60]);

        ReguaFollowUp::create([
            'nome' => 'Regra Lead Perdido Responsável em Risco',
            'gatilho' => GatilhoReguaFollowUp::RiscoEvasaoRematricula,
            'dias_offset' => 0,
            'canal' => CanalReguaFollowUp::NotificacaoSistema,
            'assunto' => 'Apoio a {{NOME_ALUNO}}',
            'mensagem' => 'Acompanhar família de {{NOME_ALUNO}}.',
            'is_ativo' => true,
        ]);

        $service = app(ReguaFollowUpService::class);
        $resultado = $service->processarReguaDiaria(now(), dryRun: false);

        $this->assertEquals(1, $resultado['total_notificacoes_enviadas']);
    }

    public function test_lead_de_outra_familia_com_crianca_de_mesmo_nome_gera_zero_candidatos(): void
    {
        Carbon::setTestNow('2026-10-15 10:00:00');
        [$consultor, $status, $origem] = $this->criarEstruturaBase();

        // Aluno em risco pertence à Família A (sem lead no CRM)
        $alunoA = Pessoa::factory()->create(['nome' => 'Pedro Homonimo']);
        $maeA = Pessoa::factory()->create(['nome' => 'Mae A']);
        $alunoA->responsaveis()->attach($maeA->id);

        $turma = Turma::factory()->create();
        Matricula::factory()->create([
            'pessoa_id' => $alunoA->id,
            'turma_id' => $turma->id,
            'situacao' => SituacaoMatricula::ATIVA,
            'risco_evasao_score' => 90,
        ]);

        // Família B (sem vínculo com o aluno da escola) tem um lead cujo dependente tem o mesmo nome
        $maeB = Pessoa::factory()->create(['nome' => 'Mae B Totalmente Distinta']);
        $leadB = Interessado::create([
            'pessoa_id' => $maeB->id,
            'status_interessado_id' => $status->id,
            'origem_interessado_id' => $origem->id,
            'usuario_id' => $consultor->id,
        ]);
        InteressadoDependente::create([
            'interessado_id' => $leadB->id,
            'nome_crianca' => 'Pedro Homonimo',
        ]);

        config(['risco_evasao.playbook.corte_risco' => 60]);

        ReguaFollowUp::create([
            'nome' => 'Regra Evasao',
            'gatilho' => GatilhoReguaFollowUp::RiscoEvasaoRematricula,
            'dias_offset' => 0,
            'canal' => CanalReguaFollowUp::NotificacaoSistema,
            'assunto' => 'Apoio',
            'mensagem' => 'Msg',
            'is_ativo' => true,
        ]);

        $service = app(ReguaFollowUpService::class);
        $resultado = $service->processarReguaDiaria(now(), dryRun: false);

        // O lead da família B NÃO deve ser acionado porque não é responsável do aluno
        $this->assertEquals(0, $resultado['total_notificacoes_enviadas']);
    }

    public function test_crianca_com_acento_nao_casa_por_nome(): void
    {
        Carbon::setTestNow('2026-10-15 10:00:00');
        [$consultor, $status, $origem] = $this->criarEstruturaBase();

        $aluno = Pessoa::factory()->create(['nome' => 'João Vitória']);
        $maeReal = Pessoa::factory()->create(['nome' => 'Mãe Real']);
        $aluno->responsaveis()->attach($maeReal->id);

        $turma = Turma::factory()->create();
        Matricula::factory()->create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'situacao' => SituacaoMatricula::ATIVA,
            'risco_evasao_score' => 85,
        ]);

        // Outra família com dependente de mesmo nome
        $outraMae = Pessoa::factory()->create(['nome' => 'Outra Mãe']);
        $leadOutro = Interessado::create([
            'pessoa_id' => $outraMae->id,
            'status_interessado_id' => $status->id,
            'origem_interessado_id' => $origem->id,
            'usuario_id' => $consultor->id,
        ]);
        InteressadoDependente::create([
            'interessado_id' => $leadOutro->id,
            'nome_crianca' => 'João Vitória',
        ]);

        config(['risco_evasao.playbook.corte_risco' => 60]);

        ReguaFollowUp::create([
            'nome' => 'Regra Evasao',
            'gatilho' => GatilhoReguaFollowUp::RiscoEvasaoRematricula,
            'dias_offset' => 0,
            'canal' => CanalReguaFollowUp::NotificacaoSistema,
            'assunto' => 'Apoio',
            'mensagem' => 'Msg',
            'is_ativo' => true,
        ]);

        $service = app(ReguaFollowUpService::class);
        $resultado = $service->processarReguaDiaria(now(), dryRun: false);

        // Não casa por nome
        $this->assertEquals(0, $resultado['total_notificacoes_enviadas']);
    }

    public function test_dois_leads_da_mesma_pessoa_id_deduplica_mantendo_o_mais_recente(): void
    {
        Carbon::setTestNow('2026-10-15 10:00:00');
        [$consultor, $status, $origem] = $this->criarEstruturaBase();

        $aluno = Pessoa::factory()->create(['nome' => 'Larissa Aluna']);
        $mae = Pessoa::factory()->create(['nome' => 'Renata Mae', 'email' => 'renata@example.com']);
        $aluno->responsaveis()->attach($mae->id);

        $turma = Turma::factory()->create();
        Matricula::factory()->create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'situacao' => SituacaoMatricula::ATIVA,
            'risco_evasao_score' => 85,
        ]);

        // Dois leads para a mesma mãe
        $leadAntigo = Interessado::create([
            'pessoa_id' => $mae->id,
            'status_interessado_id' => $status->id,
            'origem_interessado_id' => $origem->id,
            'usuario_id' => $consultor->id,
            'created_at' => now()->subMonths(3),
        ]);

        $leadRecente = Interessado::create([
            'pessoa_id' => $mae->id,
            'status_interessado_id' => $status->id,
            'origem_interessado_id' => $origem->id,
            'usuario_id' => $consultor->id,
            'created_at' => now()->subDay(),
        ]);

        config(['risco_evasao.playbook.corte_risco' => 60]);

        ReguaFollowUp::create([
            'nome' => 'Regra Evasao',
            'gatilho' => GatilhoReguaFollowUp::RiscoEvasaoRematricula,
            'dias_offset' => 0,
            'canal' => CanalReguaFollowUp::NotificacaoSistema,
            'assunto' => 'Apoio {{NOME_ALUNO}}',
            'mensagem' => 'Msg',
            'is_ativo' => true,
        ]);

        $service = app(ReguaFollowUpService::class);
        $metodo = new \ReflectionMethod($service, 'candidatosDeRiscoEvasaoRematricula');
        $metodo->setAccessible(true);
        $regra = ReguaFollowUp::first();
        $candidatos = $metodo->invoke($service, now(), $regra);

        // Deve retornar exatamente 1 candidato
        $this->assertCount(1, $candidatos);
        // O lead mantido deve ser o mais recente
        $this->assertEquals($leadRecente->id, $candidatos->first()['interessado']->id);
    }
}
