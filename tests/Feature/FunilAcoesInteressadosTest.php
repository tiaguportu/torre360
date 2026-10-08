<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\EnrollmentWizard;
use App\Filament\Resources\Interessados\Pages\KanbanInteressados;
use App\Filament\Resources\Interessados\Pages\ListInteressados;
use App\Models\Concorrente;
use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\InteressadoDependente;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Models\User;
use App\Services\ConviteMatriculaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

/**
 * Travas do funil nas telas: Kanban (arrastar), ações de linha e edição em lote passam todas por
 * `LeadFunilService`, então as mesmas regras valem em qualquer caminho.
 */
class FunilAcoesInteressadosTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    private StatusInteressado $novo;

    private StatusInteressado $atendimento;

    private StatusInteressado $matriculado;

    private StatusInteressado $perdido;

    private OrigemInteressado $origem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->novo = StatusInteressado::create(['nome' => 'Novo', 'cor' => 'info', 'ordem' => 1, 'is_final' => false, 'is_ganho' => false]);
        $this->atendimento = StatusInteressado::create(['nome' => 'Em Atendimento', 'cor' => 'warning', 'ordem' => 2, 'is_final' => false, 'is_ganho' => false]);
        $this->matriculado = StatusInteressado::create(['nome' => 'Matriculado', 'cor' => 'success', 'ordem' => 3, 'is_final' => true, 'is_ganho' => true]);
        $this->perdido = StatusInteressado::create(['nome' => 'Perdido', 'cor' => 'danger', 'ordem' => 4, 'is_final' => true, 'is_ganho' => false]);
        $this->origem = OrigemInteressado::create(['nome' => 'Site']);
    }

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['activated_at' => now()->subDay()]);
        $admin->assignRole('super_admin');

        return $admin;
    }

    /**
     * Pode editar leads, mas não tem acesso ao Assistente de Matrícula.
     */
    private function consultorSemAssistente(): User
    {
        $usuario = User::factory()->create(['activated_at' => now()->subDay()]);

        foreach (['ViewAny:Interessado', 'View:Interessado', 'Update:Interessado'] as $permissao) {
            $usuario->givePermissionTo(Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'web']));
        }

        return $usuario;
    }

    private function criarLead(?StatusInteressado $status = null, array $atributos = []): Interessado
    {
        return Interessado::create($atributos + [
            'pessoa_id' => Pessoa::factory()->create()->id,
            'status_interessado_id' => ($status ?? $this->novo)->id,
            'origem_interessado_id' => $this->origem->id,
        ]);
    }

    // ─── Kanban ─────────────────────────────────────────────────

    public function test_arrastar_para_matriculado_leva_ao_assistente_sem_converter_o_lead(): void
    {
        $lead = $this->criarLead();

        Livewire::actingAs($this->admin())
            ->test(KanbanInteressados::class)
            ->call('updateRecordStatus', $lead->id, $this->matriculado->id)
            ->assertRedirect(EnrollmentWizard::getUrl(['interessado' => $lead->id]))
            ->assertNotified('Conclua a matrícula');

        $lead->refresh();
        $this->assertSame($this->novo->id, $lead->status_interessado_id);
        $this->assertNull($lead->data_conversao);
    }

    public function test_sem_acesso_ao_assistente_arrastar_para_matriculado_usa_o_atalho_de_marcar_matriculado(): void
    {
        $lead = $this->criarLead();

        Livewire::actingAs($this->consultorSemAssistente())
            ->test(KanbanInteressados::class)
            ->call('updateRecordStatus', $lead->id, $this->matriculado->id)
            ->assertNoRedirect()
            ->assertNotified('Lead marcado como matriculado!');

        $lead->refresh();
        $this->assertSame($this->matriculado->id, $lead->status_interessado_id);
        $this->assertNotNull($lead->data_conversao);
    }

    public function test_lead_matriculado_nao_pode_ser_arrastado_de_volta(): void
    {
        $lead = $this->criarLead($this->matriculado, ['data_conversao' => now()->subDay()]);

        Livewire::actingAs($this->admin())
            ->test(KanbanInteressados::class)
            ->call('updateRecordStatus', $lead->id, $this->atendimento->id)
            ->assertNotified('Lead já matriculado')
            ->call('updateRecordStatus', $lead->id, $this->perdido->id)
            ->assertSet('modalPerdaAberto', false);

        $lead->refresh();
        $this->assertSame($this->matriculado->id, $lead->status_interessado_id);
        $this->assertNotNull($lead->data_conversao);
    }

    public function test_soltar_o_card_na_mesma_coluna_nao_gera_movimentacao(): void
    {
        $lead = $this->criarLead($this->atendimento);

        Livewire::actingAs($this->admin())
            ->test(KanbanInteressados::class)
            ->call('updateRecordStatus', $lead->id, $this->atendimento->id)
            ->assertNotNotified('Status atualizado!');

        $this->assertSame(0, HistoricoContato::count());
    }

    public function test_registros_do_funil_nao_se_passam_por_visita_presencial(): void
    {
        $lead = $this->criarLead();
        TipoContatoInteressado::create(['nome' => 'Presencial']);

        Livewire::actingAs($this->admin())
            ->test(KanbanInteressados::class)
            ->call('updateRecordStatus', $lead->id, $this->perdido->id)
            ->set('motivoPerda', 'Preço')
            ->call('confirmarPerda');

        $historico = HistoricoContato::with('tipoContato')->firstOrFail();

        $this->assertSame(TipoContatoInteressado::FUNIL, $historico->tipoContato->nome);
    }

    // ─── Ações de linha ─────────────────────────────────────────

    public function test_acao_perdido_da_tabela_usa_o_servico_e_grava_a_escola_concorrente_dos_battlecards(): void
    {
        $lead = $this->criarLead();
        $concorrente = Concorrente::create(['nome' => 'Colégio São Francisco']);

        Livewire::actingAs($this->admin())
            ->test(ListInteressados::class)
            ->callTableAction('marcarPerdido', $lead, data: [
                'motivo_perda' => 'Concorrência',
                'concorrente_id' => $concorrente->id,
                'fator_decisivo_concorrente' => 'Preço / Bolsa',
                'observacoes_perda' => 'Escolheu pela localização',
            ])
            ->assertHasNoTableActionErrors();

        $lead->refresh();
        $this->assertSame($this->perdido->id, $lead->status_interessado_id);
        $this->assertSame('Concorrência: Colégio São Francisco', $lead->motivo_perda);
        $this->assertSame($concorrente->id, $lead->concorrente_id);
        $this->assertSame('Preço / Bolsa', $lead->fator_decisivo_concorrente);
        $this->assertSame('Escolheu pela localização', $lead->detalhes_concorrencia);

        $historico = HistoricoContato::firstOrFail();
        $this->assertStringContainsString('Fator decisivo: Preço / Bolsa.', $historico->relato);
        $this->assertStringContainsString('Detalhes: Escolheu pela localização', $historico->relato);
        $this->assertSame('sem_interesse', $historico->resultado);
    }

    public function test_perda_por_preco_com_concorrente_mantem_o_motivo_padronizado_e_cita_a_escola_no_relato(): void
    {
        $lead = $this->criarLead();
        $concorrente = Concorrente::create(['nome' => 'Escola Alfa']);

        Livewire::actingAs($this->admin())
            ->test(ListInteressados::class)
            ->callTableAction('marcarPerdido', $lead, data: [
                'motivo_perda' => 'Preço',
                'concorrente_id' => $concorrente->id,
            ])
            ->assertHasNoTableActionErrors();

        $lead->refresh();
        $this->assertSame('Preço', $lead->motivo_perda, 'Só o motivo Concorrência leva o nome da escola no texto.');
        $this->assertSame($concorrente->id, $lead->concorrente_id);
        $this->assertStringContainsString('Escola concorrente: Escola Alfa.', HistoricoContato::firstOrFail()->relato);
    }

    public function test_kanban_grava_concorrente_e_fator_decisivo_pelo_mesmo_servico(): void
    {
        $lead = $this->criarLead();
        $concorrente = Concorrente::create(['nome' => 'Colégio Beta']);

        Livewire::actingAs($this->admin())
            ->test(KanbanInteressados::class)
            ->call('updateRecordStatus', $lead->id, $this->perdido->id)
            ->set('motivoPerda', 'Concorrência')
            ->set('concorrenteId', $concorrente->id)
            ->set('fatorDecisivoConcorrente', 'Metodologia Pedagógica')
            ->set('observacoesPerda', 'Preferiu a linha construtivista')
            ->call('confirmarPerda')
            ->assertHasNoErrors();

        $lead->refresh();
        $this->assertSame('Concorrência: Colégio Beta', $lead->motivo_perda);
        $this->assertSame($concorrente->id, $lead->concorrente_id);
        $this->assertSame('Metodologia Pedagógica', $lead->fator_decisivo_concorrente);
        $this->assertSame('Preferiu a linha construtivista', $lead->detalhes_concorrencia);
        $this->assertStringContainsString('Fator decisivo: Metodologia Pedagógica.', HistoricoContato::firstOrFail()->relato);
    }

    public function test_acao_perdido_sem_etapa_de_perda_cadastrada_avisa_em_vez_de_fingir_sucesso(): void
    {
        $lead = $this->criarLead();
        $this->perdido->delete();

        Livewire::actingAs($this->admin())
            ->test(ListInteressados::class)
            ->callTableAction('marcarPerdido', $lead, data: ['motivo_perda' => 'Preço'])
            ->assertNotified('Não há etapa de perda cadastrada');

        $this->assertSame($this->novo->id, $lead->fresh()->status_interessado_id);
        $this->assertSame(0, HistoricoContato::count());
    }

    public function test_acao_perdido_exige_permissao_de_edicao(): void
    {
        $lead = $this->criarLead();
        $somenteLeitura = User::factory()->create(['activated_at' => now()->subDay()]);

        foreach (['ViewAny:Interessado', 'View:Interessado'] as $permissao) {
            $somenteLeitura->givePermissionTo(Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'web']));
        }

        Livewire::actingAs($somenteLeitura)
            ->test(ListInteressados::class)
            ->assertTableActionHidden('marcarPerdido', $lead);

        $this->assertSame($this->novo->id, $lead->fresh()->status_interessado_id);
    }

    public function test_marcar_matriculado_sem_acesso_ao_assistente_converte_pelo_servico(): void
    {
        $lead = $this->criarLead($this->atendimento);

        Livewire::actingAs($this->consultorSemAssistente())
            ->test(ListInteressados::class)
            ->callTableAction('finalizarMatricula', $lead);

        $lead->refresh();
        $this->assertSame($this->matriculado->id, $lead->status_interessado_id);
        $this->assertNotNull($lead->data_conversao);
    }

    public function test_marcar_matriculado_sem_etapa_de_ganho_nao_deixa_o_lead_sem_status(): void
    {
        $lead = $this->criarLead($this->atendimento);
        $this->matriculado->delete();

        Livewire::actingAs($this->consultorSemAssistente())
            ->test(ListInteressados::class)
            ->callTableAction('finalizarMatricula', $lead)
            ->assertNotified('Não foi possível marcar como matriculado');

        $lead->refresh();
        $this->assertSame($this->atendimento->id, $lead->status_interessado_id);
        $this->assertNull($lead->data_conversao);
    }

    public function test_registrar_atendimento_permite_informar_quando_o_contato_aconteceu(): void
    {
        $tipo = TipoContatoInteressado::create(['nome' => 'WhatsApp']);
        $lead = $this->criarLead();
        $quando = now()->subDays(2)->startOfMinute();

        Livewire::actingAs($this->admin())
            ->test(ListInteressados::class)
            ->callTableAction('registrarAtendimento', $lead, data: [
                'tipo_contato_interessado_id' => $tipo->id,
                'data_contato' => $quando,
                'relato' => 'Conversamos por mensagem.',
                'data_proximo_contato' => now()->addDays(2),
            ])
            ->assertHasNoTableActionErrors();

        $historico = HistoricoContato::firstOrFail();
        $this->assertEquals($quando->timestamp, $historico->data_contato->timestamp);
        $this->assertFalse($historico->automatico);
        $this->assertNotNull($lead->fresh()->data_primeiro_contato);
    }

    // ─── Edição em lote ─────────────────────────────────────────

    public function test_lote_para_etapa_de_perda_sem_motivo_nao_altera_nenhum_lead(): void
    {
        $leads = [$this->criarLead(), $this->criarLead()];

        Livewire::actingAs($this->admin())
            ->test(ListInteressados::class)
            ->callTableBulkAction('editarLote', $leads, data: ['status_interessado_id' => $this->perdido->id])
            ->assertNotified('Informe o motivo da perda');

        foreach ($leads as $lead) {
            $this->assertSame($this->novo->id, $lead->fresh()->status_interessado_id);
            $this->assertNull($lead->fresh()->motivo_perda);
        }

        $this->assertSame(0, HistoricoContato::count());
    }

    public function test_lote_para_etapa_de_perda_com_motivo_passa_pelo_servico_e_deixa_historico(): void
    {
        $leads = [$this->criarLead(), $this->criarLead()];

        Livewire::actingAs($this->admin())
            ->test(ListInteressados::class)
            ->callTableBulkAction('editarLote', $leads, data: [
                'status_interessado_id' => $this->perdido->id,
                'motivo_perda' => 'Distância',
            ])
            ->assertNotified('2 lead(s) atualizado(s) com sucesso!');

        foreach ($leads as $lead) {
            $this->assertSame($this->perdido->id, $lead->fresh()->status_interessado_id);
            $this->assertSame('Distância', $lead->fresh()->motivo_perda);
        }

        $this->assertSame(2, HistoricoContato::where('resultado', 'sem_interesse')->count());
    }

    public function test_lote_nao_matricula_leads(): void
    {
        $lead = $this->criarLead();

        Livewire::actingAs($this->admin())
            ->test(ListInteressados::class)
            ->callTableBulkAction('editarLote', [$lead], data: ['status_interessado_id' => $this->matriculado->id]);

        $lead->refresh();
        $this->assertSame($this->novo->id, $lead->status_interessado_id);
        $this->assertNull($lead->data_conversao);
    }

    public function test_lote_pula_leads_ja_matriculados_e_avisa(): void
    {
        $ativo = $this->criarLead();
        $matriculado = $this->criarLead($this->matriculado, ['data_conversao' => now()->subDay(), 'temperatura' => 'frio']);

        Livewire::actingAs($this->admin())
            ->test(ListInteressados::class)
            ->callTableBulkAction('editarLote', [$ativo, $matriculado], data: [
                'status_interessado_id' => $this->atendimento->id,
                'temperatura' => 'quente',
            ])
            ->assertNotified('1 lead(s) atualizado(s) com sucesso!');

        $this->assertSame($this->atendimento->id, $ativo->fresh()->status_interessado_id);
        $this->assertSame('quente', $ativo->fresh()->temperatura);

        $this->assertSame($this->matriculado->id, $matriculado->fresh()->status_interessado_id);
        $this->assertSame('frio', $matriculado->fresh()->temperatura, 'Lead matriculado não recebe nenhuma alteração do lote.');
    }

    public function test_motivo_de_perda_sozinho_no_lote_so_corrige_leads_ja_perdidos(): void
    {
        $perdido = $this->criarLead($this->perdido, ['motivo_perda' => 'Preço']);
        $ativo = $this->criarLead($this->atendimento);

        Livewire::actingAs($this->admin())
            ->test(ListInteressados::class)
            ->callTableBulkAction('editarLote', [$perdido, $ativo], data: ['motivo_perda' => 'Distância']);

        $this->assertSame('Distância', $perdido->fresh()->motivo_perda);
        $this->assertNull($ativo->fresh()->motivo_perda);
    }

    // ─── Atribuição de consultor ────────────────────────────────

    public function test_atribuir_consultor_avisa_quem_recebeu_os_leads(): void
    {
        $consultor = $this->consultorSemAssistente();
        $leads = [$this->criarLead(), $this->criarLead()];

        Livewire::actingAs($this->admin())
            ->test(ListInteressados::class)
            ->callTableBulkAction('atribuirConsultor', $leads, data: ['usuario_id' => $consultor->id]);

        foreach ($leads as $lead) {
            $this->assertSame($consultor->id, $lead->fresh()->usuario_id);
        }

        $titulos = $consultor->notifications()->get()->pluck('data.title');
        $this->assertTrue($titulos->contains('2 leads foram atribuídos a você'));
    }

    public function test_atribuir_consultor_recusa_usuario_sem_permissao_de_atendimento(): void
    {
        $familia = User::factory()->create(['activated_at' => now()->subDay()]);
        $lead = $this->criarLead();

        Livewire::actingAs($this->admin())
            ->test(ListInteressados::class)
            ->callTableBulkAction('atribuirConsultor', [$lead], data: ['usuario_id' => $familia->id]);

        $this->assertNull($lead->fresh()->usuario_id);
        $this->assertSame(0, $familia->notifications()->count());
    }

    // ─── Convite de pré-matrícula (efeito colateral fora do form) ─

    public function test_abrir_o_link_de_pre_matricula_repetidas_vezes_nao_troca_o_token(): void
    {
        $lead = $this->criarLead();
        InteressadoDependente::create(['interessado_id' => $lead->id, 'nome_crianca' => 'Lucas']);

        $tela = Livewire::actingAs($this->admin())->test(ListInteressados::class);

        // O modal mostra o link do Portal de Admissão (token_documentos), válido por 7 dias.
        $tela->callTableAction('gerarConvite', $lead);
        $primeiro = $lead->fresh()->token_documentos;
        $this->assertNotNull($primeiro);

        $tela->callTableAction('gerarConvite', $lead);
        $tela->mountTableAction('gerarConvite', $lead);

        $this->assertSame($primeiro, $lead->fresh()->token_documentos, 'Abrir o modal de novo não pode invalidar o link já enviado.');
    }

    public function test_gerar_novo_link_invalida_o_anterior(): void
    {
        $lead = $this->criarLead();
        InteressadoDependente::create(['interessado_id' => $lead->id, 'nome_crianca' => 'Lucas']);

        $tela = Livewire::actingAs($this->admin())->test(ListInteressados::class);

        $tela->callTableAction('gerarConvite', $lead);
        $primeiro = $lead->fresh()->token_documentos;

        $tela->callTableAction('regenerarConvite', $lead);

        $segundo = $lead->fresh()->token_documentos;
        $this->assertNotSame($primeiro, $segundo);

        // A URL que a família já tinha deixa de funcionar na hora; só a nova abre o portal.
        $this->get(route('candidato.documentos.show', ['token' => $primeiro]))->assertStatus(410);
        $this->get(route('candidato.documentos.show', ['token' => $segundo]))->assertOk();
    }

    public function test_acao_de_gerar_novo_link_so_aparece_quando_ja_existe_um_link(): void
    {
        $lead = $this->criarLead();
        InteressadoDependente::create(['interessado_id' => $lead->id, 'nome_crianca' => 'Lucas']);

        $tela = Livewire::actingAs($this->admin())->test(ListInteressados::class);

        $tela->assertTableActionHidden('regenerarConvite', $lead);

        app(ConviteMatriculaService::class)->gerarConvite($lead);

        Livewire::actingAs($this->admin())
            ->test(ListInteressados::class)
            ->assertTableActionVisible('regenerarConvite', $lead->fresh());
    }

    public function test_obter_ou_gerar_convite_reaproveita_link_valido_e_gera_outro_quando_usado_ou_expirado(): void
    {
        $servico = app(ConviteMatriculaService::class);
        $lead = $this->criarLead();

        $link = $servico->obterOuGerarConvite($lead);
        $this->assertSame($link, $servico->obterOuGerarConvite($lead->fresh()));

        $lead->fresh()->update(['token_convite_usado_em' => now()]);
        $this->assertNotSame($link, $servico->obterOuGerarConvite($lead->fresh()));

        $novoLink = $servico->obterOuGerarConvite($lead->fresh());
        $lead->fresh()->update(['token_convite_expira_em' => now()->subMinute()]);
        $this->assertNotSame($novoLink, $servico->obterOuGerarConvite($lead->fresh()));
    }
}
