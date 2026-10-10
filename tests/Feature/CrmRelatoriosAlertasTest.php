<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatusVisitaInteressado;
use App\Filament\Pages\CrmRelatoriosPage;
use App\Models\Concorrente;
use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\PesquisaSatisfacaoVisita;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Models\User;
use App\Models\VisitaInteressado;
use App\Services\CrmRelatoriosService;
use App\Services\LeadFunilService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

/**
 * Testes dos relatórios gerenciais e alertas do CRM (Lote D2):
 *  - Alerta de NPS detrator na visita (uma vez por pesquisa, notifica consultor e gestão).
 *  - Motivos de perda agregados com concorrente em coluna própria.
 *  - Desempenho por consultor (tempo 1ª resposta, visitas, contatos, conversão).
 *  - Previsão de receita ponderada baseada no valor estimado dos leads e probabilidade por etapa.
 *  - Acesso e renderização da tela Filament CrmRelatoriosPage.
 */
class CrmRelatoriosAlertasTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    private StatusInteressado $novo;

    private StatusInteressado $atendimento;

    private StatusInteressado $matriculado;

    private StatusInteressado $perdido;

    private OrigemInteressado $origem;

    private User $consultor;

    private User $admin;

    private CrmRelatoriosService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->novo = StatusInteressado::create(['nome' => 'Novo', 'cor' => 'info', 'ordem' => 1, 'is_final' => false, 'is_ganho' => false]);
        $this->atendimento = StatusInteressado::create(['nome' => 'Em Atendimento', 'cor' => 'warning', 'ordem' => 2, 'is_final' => false, 'is_ganho' => false]);
        $this->matriculado = StatusInteressado::create(['nome' => 'Matriculado', 'cor' => 'success', 'ordem' => 3, 'is_final' => true, 'is_ganho' => true]);
        $this->perdido = StatusInteressado::create(['nome' => 'Perdido', 'cor' => 'danger', 'ordem' => 4, 'is_final' => true, 'is_ganho' => false]);

        $this->origem = OrigemInteressado::create(['nome' => 'Site']);

        // Roles e permissões para consultor e admin
        $roleAdmin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $roleSuper = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $permConsultor = Permission::firstOrCreate(['name' => 'Update:Interessado', 'guard_name' => 'web']);

        $this->admin = User::factory()->create(['name' => 'Diretora Maria', 'activated_at' => now()]);
        $this->admin->assignRole($roleAdmin);

        $this->consultor = User::factory()->create(['name' => 'Consultor Carlos', 'activated_at' => now()]);
        $this->consultor->givePermissionTo($permConsultor);

        $this->service = app(CrmRelatoriosService::class);
    }

    private function criarLead(array $atributos = []): Interessado
    {
        return Interessado::create($atributos + [
            'pessoa_id' => Pessoa::factory()->create()->id,
            'status_interessado_id' => $this->novo->id,
            'origem_interessado_id' => $this->origem->id,
            'usuario_id' => $this->consultor->id,
        ]);
    }

    public function test_pesquisa_nps_detrator_dispara_alerta_ao_consultor_e_a_gestao_uma_unica_vez(): void
    {
        $lead = $this->criarLead();

        $visita = VisitaInteressado::create([
            'interessado_id' => $lead->id,
            'usuario_id' => $this->consultor->id,
            'data_hora' => now()->subDay(),
            'status' => StatusVisitaInteressado::Realizada,
        ]);

        $pesquisa = PesquisaSatisfacaoVisita::create([
            'visita_interessado_id' => $visita->id,
            'interessado_id' => $lead->id,
            'token' => 'token-pesquisa-teste-123',
            'nota_nps' => 4, // Detrator (< 7)
            'comentario' => 'Achei as salas um pouco pequenas.',
            'respondido_em' => now(),
        ]);

        $this->assertNull($pesquisa->alerta_detrator_enviado_em);

        // Dispara o alerta
        $disparou = $pesquisa->dispararAlertaDetratorSeNecessario();

        $this->assertTrue($disparou);
        $this->assertNotNull($pesquisa->fresh()->alerta_detrator_enviado_em);

        // Notificação salva no banco para o consultor e para o admin
        $notificacoesConsultor = $this->consultor->notifications()->get();
        $notificacoesAdmin = $this->admin->notifications()->get();

        $this->assertGreaterThanOrEqual(1, $notificacoesConsultor->count());
        $this->assertGreaterThanOrEqual(1, $notificacoesAdmin->count());
        $this->assertStringContainsString('Avaliação Detratora', (string) $notificacoesConsultor->first()?->data['title']);

        // Tentativa subsequente de disparo não deve reenviar (uma vez por pesquisa)
        $disparouNovamente = $pesquisa->fresh()->dispararAlertaDetratorSeNecessario();
        $this->assertFalse($disparouNovamente);
    }

    public function test_pesquisa_nps_promotor_nao_dispara_alerta_detrator(): void
    {
        $lead = $this->criarLead();

        $visita = VisitaInteressado::create([
            'interessado_id' => $lead->id,
            'usuario_id' => $this->consultor->id,
            'data_hora' => now()->subDay(),
            'status' => StatusVisitaInteressado::Realizada,
        ]);

        $pesquisa = PesquisaSatisfacaoVisita::create([
            'visita_interessado_id' => $visita->id,
            'interessado_id' => $lead->id,
            'token' => 'token-promotor-123',
            'nota_nps' => 10, // Promotor
            'respondido_em' => now(),
        ]);

        $disparou = $pesquisa->dispararAlertaDetratorSeNecessario();

        $this->assertFalse($disparou);
        $this->assertNull($pesquisa->fresh()->alerta_detrator_enviado_em);
    }

    public function test_motivos_de_perda_agregam_corretamente_e_concorrente_tem_coluna_propria(): void
    {
        $concorrenteA = Concorrente::create(['nome' => 'Colégio Anglo']);
        $concorrenteB = Concorrente::create(['nome' => 'Escola Futuro']);

        $funil = app(LeadFunilService::class);

        // Lead 1: Perda por Preço
        $l1 = $this->criarLead();
        $funil->marcarComoPerdido($l1, $this->perdido, 'Preço');

        // Lead 2: Perda para concorrente A com FK e fator decisivo
        $l2 = $this->criarLead();
        $funil->marcarComoPerdido(
            lead: $l2,
            status: $this->perdido,
            motivo: 'Concorrência',
            concorrente: $concorrenteA->nome,
            atributosExtras: [
                'concorrente_id' => $concorrenteA->id,
                'fator_decisivo_concorrente' => 'Mensalidade mais em conta',
            ]
        );

        // Lead 3: Perda para concorrente B
        $l3 = $this->criarLead();
        $funil->marcarComoPerdido(
            lead: $l3,
            status: $this->perdido,
            motivo: 'Concorrência',
            concorrente: $concorrenteB->nome,
            atributosExtras: [
                'concorrente_id' => $concorrenteB->id,
                'fator_decisivo_concorrente' => 'Mais perto de casa',
            ]
        );

        $resultado = $this->service->motivosPerda();

        $this->assertSame(3, $resultado['total_perdas']);

        $motivos = $resultado['motivos'];
        $motivoConcorrencia = $motivos->firstWhere('motivo', 'Concorrência');
        $this->assertNotNull($motivoConcorrencia);
        $this->assertSame(2, $motivoConcorrencia['total']);

        $motivoPreco = $motivos->firstWhere('motivo', 'Preço');
        $this->assertNotNull($motivoPreco);
        $this->assertSame(1, $motivoPreco['total']);

        // Tabela de concorrentes em coluna própria
        $concorrentes = $resultado['concorrentes'];
        $this->assertCount(2, $concorrentes);

        $anglo = $concorrentes->firstWhere('concorrente_nome', 'Colégio Anglo');
        $this->assertNotNull($anglo);
        $this->assertSame($concorrenteA->id, $anglo['concorrente_id']);
        $this->assertSame(1, $anglo['total_perdas']);
        $this->assertSame('Mensalidade mais em conta', $anglo['fator_decisivo_principal']);
    }

    public function test_desempenho_por_consultor_apura_1a_resposta_visitas_contatos_e_conversao(): void
    {
        $hoje = Carbon::today()->setHour(8);

        // Lead 1: Carlos atendeu 4 horas após a criação e matriculou
        $l1 = $this->criarLead([
            'usuario_id' => $this->consultor->id,
            'data_primeiro_contato' => $hoje->copy()->addHours(4),
        ]);
        $l1->forceFill(['created_at' => $hoje])->saveQuietly();
        app(LeadFunilService::class)->marcarMatriculado($l1);

        // Visita realizada
        VisitaInteressado::create([
            'interessado_id' => $l1->id,
            'usuario_id' => $this->consultor->id,
            'data_hora' => $hoje->copy()->addHours(24),
            'status' => StatusVisitaInteressado::Realizada,
        ]);

        // Contato humano
        $tipoContato = TipoContatoInteressado::porNome('Telefone') ?? TipoContatoInteressado::create(['nome' => 'Telefone']);
        HistoricoContato::create([
            'interessado_id' => $l1->id,
            'usuario_id' => $this->consultor->id,
            'tipo_contato_interessado_id' => $tipoContato->id,
            'data_contato' => $hoje->copy()->addHours(4),
            'relato' => 'Conversa com a mãe',
            'automatico' => false,
        ]);

        $desempenho = $this->service->desempenhoConsultores();

        $carlos = $desempenho->firstWhere('id', $this->consultor->id);
        $this->assertNotNull($carlos);
        $this->assertSame(1, $carlos['total_leads']);
        $this->assertEquals(4.0, $carlos['tempo_medio_primeira_resposta_horas']);
        $this->assertSame(1, $carlos['total_contatos']);
        $this->assertSame(1, $carlos['total_visitas']);
        $this->assertSame(1, $carlos['ganhos']);
        $this->assertEquals(100.0, $carlos['taxa_conversao']);
    }

    public function test_previsao_de_receita_calcula_ponderado_com_valor_estimado_e_ticket_medio_fallback(): void
    {
        config([
            'crm.previsao_receita.ticket_medio_padrao' => 2000.00,
            'crm.previsao_receita.probabilidades_etapa' => [
                'Novo' => 10.0,
                'Em Atendimento' => 30.0,
            ],
        ]);

        // Lead 1 em Novo: com valor informado de 3.000,00
        $this->criarLead([
            'status_interessado_id' => $this->novo->id,
            'valor_estimado' => 3000.00,
        ]);

        // Lead 2 em Novo: sem valor informado (deve usar fallback de 2.000,00)
        $this->criarLead([
            'status_interessado_id' => $this->novo->id,
            'valor_estimado' => null,
        ]);

        // Lead 3 em Atendimento: valor informado de 4.000,00
        $this->criarLead([
            'status_interessado_id' => $this->atendimento->id,
            'valor_estimado' => 4000.00,
        ]);

        $previsao = $this->service->previsaoReceita();

        // Total carteira = 3000 + 2000 + 4000 = 9000
        $this->assertEquals(9000.00, $previsao['total_carteira']);

        // Ponderado Novo = 5000 * 10% = 500.00
        // Ponderado Atendimento = 4000 * 30% = 1200.00
        // Total ponderado = 1700.00
        $this->assertEquals(1700.00, $previsao['total_previsto_ponderado']);
        $this->assertSame(3, $previsao['total_leads_ativos']);

        $etapaNovo = $previsao['etapas']->firstWhere('status_id', $this->novo->id);
        $this->assertEquals(5000.00, $etapaNovo['valor_carteira']);
        $this->assertEquals(500.00, $etapaNovo['valor_previsto_ponderado']);
    }

    public function test_pagina_filament_crm_relatorios_renderiza_com_sucesso(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CrmRelatoriosPage::class)
            ->assertSuccessful()
            ->assertSee('Relatórios e Desempenho do CRM')
            ->assertSee('Previsão de Receita por Etapa do Funil')
            ->assertSee('Desempenho por Consultor')
            ->assertSee('Inteligência Competitiva');
    }
}
