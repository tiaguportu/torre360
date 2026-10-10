<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CampanhaMarketing;
use App\Models\Interessado;
use App\Models\InteressadoStatusHistorico;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\User;
use App\Services\FunilAnaliticoService;
use App\Services\LeadFunilService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

/**
 * Testes do histórico de etapas do funil (Pacote D1):
 *  - Garantia de exatamente uma linha por transição (Kanban, tabela, lote, criação).
 *  - Idempotência na movimentação para mesma etapa.
 *  - Backfill de etapas estimadas.
 *  - Cálculos analíticos de tempo médio e conversão etapa a etapa.
 */
class FunilHistoricoTransicaoTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    private StatusInteressado $novo;

    private StatusInteressado $atendimento;

    private StatusInteressado $visita;

    private StatusInteressado $matriculado;

    private StatusInteressado $perdido;

    private OrigemInteressado $origemSite;

    private OrigemInteressado $origemIndicacao;

    private CampanhaMarketing $campanhaGoogle;

    private User $consultorA;

    private User $consultorB;

    private LeadFunilService $funil;

    private FunilAnaliticoService $analitico;

    protected function setUp(): void
    {
        parent::setUp();

        $this->novo = StatusInteressado::create(['nome' => 'Novo', 'cor' => 'info', 'ordem' => 1, 'is_final' => false, 'is_ganho' => false]);
        $this->atendimento = StatusInteressado::create(['nome' => 'Em Atendimento', 'cor' => 'warning', 'ordem' => 2, 'is_final' => false, 'is_ganho' => false]);
        $this->visita = StatusInteressado::create(['nome' => 'Visita Agendada', 'cor' => 'primary', 'ordem' => 3, 'is_final' => false, 'is_ganho' => false]);
        $this->matriculado = StatusInteressado::create(['nome' => 'Matriculado', 'cor' => 'success', 'ordem' => 4, 'is_final' => true, 'is_ganho' => true]);
        $this->perdido = StatusInteressado::create(['nome' => 'Perdido', 'cor' => 'danger', 'ordem' => 5, 'is_final' => true, 'is_ganho' => false]);

        $this->origemSite = OrigemInteressado::create(['nome' => 'Site']);
        $this->origemIndicacao = OrigemInteressado::create(['nome' => 'Indicação']);

        $this->campanhaGoogle = CampanhaMarketing::create(['nome' => 'Google Ads 2026', 'codigo' => 'GGL26']);

        $this->consultorA = User::factory()->create(['name' => 'Consultora Ana']);
        $this->consultorB = User::factory()->create(['name' => 'Consultor Bruno']);

        $this->funil = app(LeadFunilService::class);
        $this->analitico = app(FunilAnaliticoService::class);
    }

    private function criarLead(array $atributos = []): Interessado
    {
        return Interessado::create($atributos + [
            'pessoa_id' => Pessoa::factory()->create()->id,
            'status_interessado_id' => $this->novo->id,
            'origem_interessado_id' => $this->origemSite->id,
            'usuario_id' => $this->consultorA->id,
        ]);
    }

    public function test_criacao_de_lead_registra_exatamente_uma_linha_inicial_de_historico(): void
    {
        $lead = $this->criarLead();

        $historicos = InteressadoStatusHistorico::where('interessado_id', $lead->id)->get();

        $this->assertCount(1, $historicos);
        $this->assertNull($historicos->first()->status_anterior_id);
        $this->assertSame($this->novo->id, $historicos->first()->status_novo_id);
        $this->assertFalse($historicos->first()->estimada);
    }

    public function test_movimentacao_pelo_servico_gera_exatamente_uma_linha_sem_duplicar_no_observer(): void
    {
        $lead = $this->criarLead();

        // Linha 1 gerada na criação
        $this->assertSame(1, $lead->historicoStatus()->count());

        // Movimentação ativa (como faria o Kanban ou a tabela)
        $alterou = $this->funil->moverParaEtapaAtiva($lead, $this->atendimento, $this->consultorA->id);

        $this->assertTrue($alterou);

        $historicos = $lead->historicoStatus()->get();

        // Exatamente 2 linhas no total: a inicial e a transição
        $this->assertCount(2, $historicos);

        $ultima = $historicos->last();
        $this->assertSame($this->novo->id, $ultima->status_anterior_id);
        $this->assertSame($this->atendimento->id, $ultima->status_novo_id);
        $this->assertSame($this->consultorA->id, $ultima->usuario_id);
        $this->assertNull($ultima->motivo_perda);
    }

    public function test_marcar_como_perdido_gera_exatamente_uma_linha_com_motivo(): void
    {
        $lead = $this->criarLead();

        $this->funil->marcarComoPerdido(
            lead: $lead,
            status: $this->perdido,
            motivo: 'Preço',
            observacoes: 'Achou a anuidade alta',
            usuarioId: $this->consultorB->id
        );

        $historicos = $lead->historicoStatus()->get();

        $this->assertCount(2, $historicos);

        $ultima = $historicos->last();
        $this->assertSame($this->novo->id, $ultima->status_anterior_id);
        $this->assertSame($this->perdido->id, $ultima->status_novo_id);
        $this->assertSame($this->consultorB->id, $ultima->usuario_id);
        $this->assertSame('Preço', $ultima->motivo_perda);
    }

    public function test_mover_para_mesma_etapa_nao_gera_nova_linha_de_historico(): void
    {
        $lead = $this->criarLead();

        $alterou = $this->funil->moverParaEtapaAtiva($lead, $this->novo, $this->consultorA->id);

        $this->assertFalse($alterou);
        $this->assertSame(1, $lead->historicoStatus()->count());
    }

    public function test_transicao_em_lote_gera_exatamente_uma_linha_por_lead(): void
    {
        $lead1 = $this->criarLead();
        $lead2 = $this->criarLead();

        $this->funil->moverParaEtapaAtiva($lead1, $this->atendimento, $this->consultorA->id);
        $this->funil->moverParaEtapaAtiva($lead2, $this->atendimento, $this->consultorA->id);

        $this->assertSame(2, $lead1->historicoStatus()->count());
        $this->assertSame(2, $lead2->historicoStatus()->count());
    }

    public function test_funil_analitico_calcula_tempo_medio_por_etapa(): void
    {
        $inicio = Carbon::today()->setHour(9)->setMinute(0);

        // Lead 1: ficou 2 dias em Novo e 3 dias em Atendimento
        $lead1 = $this->criarLead(['created_at' => $inicio]);

        // Ajusta data da transição inicial
        $lead1->historicoStatus()->first()->update(['data_transicao' => $inicio]);

        // Transição para Atendimento 2 dias depois
        $t1 = $inicio->copy()->addDays(2);
        InteressadoStatusHistorico::create([
            'interessado_id' => $lead1->id,
            'status_anterior_id' => $this->novo->id,
            'status_novo_id' => $this->atendimento->id,
            'usuario_id' => $this->consultorA->id,
            'data_transicao' => $t1,
        ]);

        // Transição para Visita 3 dias depois
        $t2 = $t1->copy()->addDays(3);
        InteressadoStatusHistorico::create([
            'interessado_id' => $lead1->id,
            'status_anterior_id' => $this->atendimento->id,
            'status_novo_id' => $this->visita->id,
            'usuario_id' => $this->consultorA->id,
            'data_transicao' => $t2,
        ]);

        $tempos = $this->analitico->tempoMedioPorEtapa();

        $etapaNovo = $tempos->firstWhere('status_id', $this->novo->id);
        $this->assertNotNull($etapaNovo);
        $this->assertEquals(2.0, $etapaNovo['tempo_medio_dias']);

        $etapaAtendimento = $tempos->firstWhere('status_id', $this->atendimento->id);
        $this->assertNotNull($etapaAtendimento);
        $this->assertEquals(3.0, $etapaAtendimento['tempo_medio_dias']);
    }

    public function test_funil_analitico_calcula_conversao_etapa_a_etapa_e_perdas(): void
    {
        // Cria 4 leads em Novo
        $l1 = $this->criarLead();
        $l2 = $this->criarLead();
        $l3 = $this->criarLead();
        $l4 = $this->criarLead();

        // 3 avançam para Atendimento
        $this->funil->moverParaEtapaAtiva($l1, $this->atendimento);
        $this->funil->moverParaEtapaAtiva($l2, $this->atendimento);
        $this->funil->moverParaEtapaAtiva($l3, $this->atendimento);

        // 1 perdido em Novo
        $this->funil->marcarComoPerdido($l4, $this->perdido, 'Desistência');

        // Dos 3 em Atendimento: 2 avançam para Visita, 1 perdido
        $this->funil->moverParaEtapaAtiva($l1, $this->visita);
        $this->funil->moverParaEtapaAtiva($l2, $this->visita);
        $this->funil->marcarComoPerdido($l3, $this->perdido, 'Preço');

        $relatorio = $this->analitico->conversaoEtapaAEtapa();

        $etapas = collect($relatorio['etapas']);

        $etapaNovo = $etapas->firstWhere('status_id', $this->novo->id);
        $this->assertSame(4, $etapaNovo['total_leads']);
        $this->assertSame(3, $etapaNovo['avancaram']);
        $this->assertEquals(75.0, $etapaNovo['taxa_conversao']);
        $this->assertSame(1, $etapaNovo['perdas']);

        $etapaAtend = $etapas->firstWhere('status_id', $this->atendimento->id);
        $this->assertSame(3, $etapaAtend['total_leads']);
        $this->assertSame(2, $etapaAtend['avancaram']);
        $this->assertEquals(66.7, $etapaAtend['taxa_conversao']);
        $this->assertSame(1, $etapaAtend['perdas']);
    }

    public function test_funil_analitico_conversao_por_consultor(): void
    {
        $l1 = $this->criarLead(['usuario_id' => $this->consultorA->id]);
        $l2 = $this->criarLead(['usuario_id' => $this->consultorA->id]);
        $l3 = $this->criarLead(['usuario_id' => $this->consultorB->id]);

        $this->funil->marcarMatriculado($l1);
        $this->funil->marcarComoPerdido($l2, $this->perdido, 'Distância');

        $relatorio = $this->analitico->conversaoPorDimensao('consultor');

        $ana = $relatorio->firstWhere('id', $this->consultorA->id);
        $this->assertNotNull($ana);
        $this->assertSame(2, $ana['total_leads']);
        $this->assertSame(1, $ana['ganhos']);
        $this->assertSame(1, $ana['perdidos']);
        $this->assertEquals(50.0, $ana['taxa_conversao']);

        $bruno = $relatorio->firstWhere('id', $this->consultorB->id);
        $this->assertNotNull($bruno);
        $this->assertSame(1, $bruno['total_leads']);
        $this->assertSame(0, $bruno['ganhos']);
        $this->assertEquals(0.0, $bruno['taxa_conversao']);
    }

    public function test_backfill_migration_cria_historico_estimado_para_leads_antigos_sem_duplicar(): void
    {
        $lead = $this->criarLead();

        // Apaga o histórico gerado na criação para simular lead legado anterior à migration
        InteressadoStatusHistorico::where('interessado_id', $lead->id)->delete();
        $this->assertSame(0, $lead->historicoStatus()->count());

        // Roda a migration up novamente (idempotente)
        $migration = require database_path('migrations/2026_10_10_184810_create_interessado_status_historico_table.php');
        $migration->up();

        // O lead legado deve ter recebido uma linha estimada
        $historicos = $lead->historicoStatus()->get();
        $this->assertCount(1, $historicos);
        $this->assertTrue($historicos->first()->estimada);
        $this->assertSame($this->novo->id, $historicos->first()->status_novo_id);

        // Se rodar o up() de novo, não deve duplicar
        $migration->up();
        $this->assertSame(1, $lead->historicoStatus()->count());
    }
}
