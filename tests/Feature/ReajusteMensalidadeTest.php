<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ArredondamentoReajuste;
use App\Enums\SituacaoMatricula;
use App\Enums\StatusCenarioReajuste;
use App\Enums\StatusPlanilhaLei;
use App\Enums\StatusRematricula;
use App\Exceptions\ReajusteMensalidadeException;
use App\Filament\Resources\CenarioReajustes\Pages\CreateCenarioReajuste;
use App\Filament\Resources\CenarioReajustes\Pages\EditCenarioReajuste;
use App\Filament\Resources\CenarioReajustes\Pages\ListCenarioReajustes;
use App\Filament\Resources\CenarioReajustes\Pages\ViewCenarioReajuste;
use App\Filament\Resources\CenarioReajustes\RelationManagers\ItensRelationManager;
use App\Models\BolsaConcedida;
use App\Models\CenarioReajuste;
use App\Models\Contrato;
use App\Models\Curso;
use App\Models\ListaMaterialEscolar;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\PeriodoRematricula;
use App\Models\PeriodoRematriculaPreco;
use App\Models\Pessoa;
use App\Models\PlanilhaLeiMensalidade;
use App\Models\Serie;
use App\Models\TemplateContrato;
use App\Models\TipoBolsa;
use App\Models\Turma;
use App\Models\Turno;
use App\Models\Unidade;
use App\Models\User;
use App\Services\ListaMaterialService;
use App\Services\ReajusteMensalidadeService;
use App\Services\RematriculaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Reajuste de mensalidades (Onda 44): simulação por série a partir dos contratos do período de origem, aprovação,
 * aplicação dos preços na campanha de rematrícula e comunicação às famílias.
 *
 * Cenário de referência: série "5º Ano" com contratos de R$ 12.000 e R$ 13.000 (média R$ 12.500) e série "6º Ano" com
 * contrato de R$ 9.000. "Hoje" é 21/10/2026.
 */
class ReajusteMensalidadeTest extends TestCase
{
    use RefreshDatabase;

    private PeriodoLetivo $origem;

    private PeriodoLetivo $destino;

    private Serie $quinto;

    private Serie $sexto;

    private Turma $turmaQuinto;

    private Turma $turmaSexto;

    private Turno $turno;

    private PeriodoRematricula $campanha;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-10-21 09:00:00'));
        Http::preventStrayRequests();
        config(['services.assinafy.key' => '']);

        $this->origem = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);
        $this->destino = PeriodoLetivo::create(['nome' => '2027', 'data_inicio' => '2027-02-01', 'data_fim' => '2027-12-15']);
        $unidade = Unidade::create(['nome' => 'Sede']);
        $curso = Curso::create(['nome_externo' => 'Fundamental', 'nome_interno' => 'Fundamental', 'unidade_id' => $unidade->id]);
        $this->quinto = Serie::create(['nome' => '5º Ano', 'curso_id' => $curso->id, 'sistema_avaliacao' => 'Nota']);
        $this->sexto = Serie::create(['nome' => '6º Ano', 'curso_id' => $curso->id, 'sistema_avaliacao' => 'Nota']);
        $this->turno = Turno::create(['nome' => 'Manhã', 'hora_inicio' => '07:00:00', 'hora_fim' => '12:00:00']);
        $this->turmaQuinto = Turma::create(['nome' => '5º A 2026', 'serie_id' => $this->quinto->id, 'periodo_letivo_id' => $this->origem->id]);
        $this->turmaSexto = Turma::create(['nome' => '6º A 2026', 'serie_id' => $this->sexto->id, 'periodo_letivo_id' => $this->origem->id]);

        $this->campanha = PeriodoRematricula::create([
            'nome' => 'Rematrícula 2027',
            'periodo_letivo_origem_id' => $this->origem->id,
            'periodo_letivo_destino_id' => $this->destino->id,
            'template_contrato_id' => TemplateContrato::create(['nome' => 'Contrato 2027', 'conteudo' => '<p>Termos.</p>', 'is_padrao' => true])->id,
            'valor_taxa' => 1000,
            'quantidade_parcelas_padrao' => 4,
            'valor_entrada_padrao' => 0,
            'data_inicio' => '2026-10-01',
            'data_fim' => '2027-01-31',
            'is_ativo' => true,
        ]);
    }

    private function servico(): ReajusteMensalidadeService
    {
        return app(ReajusteMensalidadeService::class);
    }

    private function aluno(Turma $turma, float $valorContrato, string $nome = 'Aluno', string $situacao = 'ativa', ?string $responsavel = 'Responsável'): Matricula
    {
        $matricula = Matricula::create(['pessoa_id' => Pessoa::create(['nome' => $nome])->id, 'turma_id' => $turma->id, 'situacao' => $situacao]);
        $contrato = Contrato::create(['matricula_id' => $matricula->id, 'valor_total' => $valorContrato]);

        if ($responsavel) {
            $contrato->responsaveisFinanceiros()->create([
                'pessoa_id' => Pessoa::create(['nome' => $responsavel, 'email' => strtolower(str_replace(' ', '.', $responsavel)).'@exemplo.com', 'telefone' => '(11) 98888-7777'])->id,
                'percentual' => 100,
            ]);
        }

        return $matricula;
    }

    private function base(): void
    {
        $this->aluno($this->turmaQuinto, 12000, 'Ana Lima', responsavel: 'Carla Lima');
        $this->aluno($this->turmaQuinto, 13000, 'Bruno Dias', responsavel: 'Davi Dias');
        $this->aluno($this->turmaSexto, 9000, 'Caio Reis', responsavel: 'Elisa Reis');
    }

    private function cenario(float $percentual = 6.5, ArredondamentoReajuste $arredondamento = ArredondamentoReajuste::Centavos, ?PlanilhaLeiMensalidade $planilha = null): CenarioReajuste
    {
        return $this->servico()->simular($this->campanha, 'Reajuste 2027', $percentual, $arredondamento, $planilha, User::factory()->create());
    }

    private function item(CenarioReajuste $cenario, Serie $serie)
    {
        return $cenario->itens()->where('serie_id', $serie->id)->firstOrFail();
    }

    // ─── Simulação ───────────────────────────────────────────────────

    public function test_simulacao_parte_da_media_dos_contratos_do_periodo_de_origem(): void
    {
        $this->base();

        $cenario = $this->cenario();

        $quinto = $this->item($cenario, $this->quinto);
        $this->assertSame('12500.00', (string) $quinto->valor_atual);
        $this->assertSame('13312.50', (string) $quinto->valor_novo);
        $this->assertSame(2, $quinto->alunos);

        $sexto = $this->item($cenario, $this->sexto);
        $this->assertSame('9000.00', (string) $sexto->valor_atual);
        $this->assertSame('9585.00', (string) $sexto->valor_novo);
        $this->assertSame(StatusCenarioReajuste::Simulacao, $cenario->status);
    }

    public function test_base_ignora_matricula_inativa_contrato_zerado_e_usa_o_contrato_mais_recente(): void
    {
        $this->aluno($this->turmaQuinto, 12000);
        $this->aluno($this->turmaQuinto, 50000, situacao: SituacaoMatricula::CANCELADA->value);
        $this->aluno($this->turmaQuinto, 0);
        $comDois = $this->aluno($this->turmaQuinto, 1000);
        Contrato::create(['matricula_id' => $comDois->id, 'valor_total' => 14000]); // mais recente

        $quinto = $this->item($this->cenario(), $this->quinto);

        $this->assertSame(2, $quinto->alunos);
        $this->assertSame('13000.00', (string) $quinto->valor_atual);
    }

    public function test_arredondamento_para_real_e_para_dezena(): void
    {
        $this->assertSame(13313.0, $this->servico()->calcularValorNovo(12500, 6.5, ArredondamentoReajuste::Real));
        $this->assertSame(13310.0, $this->servico()->calcularValorNovo(12500, 6.5, ArredondamentoReajuste::Dezena));
        $this->assertSame(13312.5, $this->servico()->calcularValorNovo(12500, 6.5, ArredondamentoReajuste::Centavos));
        $this->assertSame(1050.01, $this->servico()->calcularValorNovo(1000.01, 5.0, ArredondamentoReajuste::Centavos));
    }

    public function test_bolsa_media_por_serie(): void
    {
        $com = $this->aluno($this->turmaQuinto, 12000);
        $this->aluno($this->turmaQuinto, 12000);
        BolsaConcedida::create([
            'matricula_id' => $com->id,
            'tipo_bolsa_id' => TipoBolsa::create(['nome' => 'Irmãos', 'percentual_maximo' => 50])->id,
            'percentual' => 30,
            'data_inicio' => '2026-01-01',
            'status' => 'aprovada',
        ]);

        $this->assertSame('15.00', (string) $this->item($this->cenario(), $this->quinto)->bolsa_media);
    }

    public function test_simulacao_sem_contratos_e_percentual_fora_da_faixa_sao_recusados(): void
    {
        try {
            $this->cenario();
            $this->fail('Sem contratos.');
        } catch (ReajusteMensalidadeException $e) {
            $this->assertStringContainsString('Não há contratos', $e->getMessage());
        }

        $this->base();
        $this->expectException(ReajusteMensalidadeException::class);
        $this->expectExceptionMessage('entre');
        $this->cenario(150);
    }

    public function test_serie_com_percentual_proprio_e_valor_atual_informado_a_mao(): void
    {
        $this->base();
        $cenario = $this->cenario();
        $sexto = $this->item($cenario, $this->sexto);

        $this->servico()->atualizarItem($sexto, 10.0);
        $this->assertSame('9900.00', (string) $sexto->fresh()->valor_novo);

        $this->servico()->atualizarItem($sexto->fresh(), null, 10000);
        $this->assertSame('10650.00', (string) $sexto->fresh()->valor_novo, 'Sem percentual próprio, volta ao padrão sobre o novo valor atual.');
    }

    public function test_mudar_o_percentual_padrao_recalcula_as_series_sem_percentual_proprio(): void
    {
        $this->base();
        $cenario = $this->cenario();
        $this->servico()->atualizarItem($this->item($cenario, $this->sexto), 10.0);

        $this->servico()->atualizarCenario($cenario, 4.0, ArredondamentoReajuste::Real);

        $this->assertSame('13000.00', (string) $this->item($cenario, $this->quinto)->valor_novo);
        $this->assertSame('9900.00', (string) $this->item($cenario, $this->sexto)->valor_novo, 'O percentual próprio é mantido.');
    }

    // ─── Aprovação, aplicação e efeito na rematrícula ────────────────

    public function test_aprovado_o_cenario_nao_muda_mais(): void
    {
        $this->base();
        $cenario = $this->servico()->aprovar($this->cenario(), User::factory()->create());

        $this->assertSame(StatusCenarioReajuste::Aprovado, $cenario->status);
        $this->assertNotNull($cenario->aprovado_em);

        $this->expectException(ReajusteMensalidadeException::class);
        $this->servico()->atualizarItem($this->item($cenario, $this->quinto), 1.0);
    }

    public function test_aprovacao_registra_quem_aprovou(): void
    {
        $this->base();
        $admin = User::factory()->create();

        $cenario = $this->servico()->aprovar($this->cenario(), $admin);

        $this->assertSame($admin->id, $cenario->aprovado_por_user_id);
        $log = Activity::where('log_name', 'reajuste_mensalidade')->where('description', 'Cenário de reajuste aprovado.')->first();
        $this->assertSame($admin->id, $log->causer_id);
    }

    private function rematricular(Matricula $matricula, Turma $destino): Contrato
    {
        $rematricula = app(RematriculaService::class)->iniciarOuObter($matricula, $this->campanha, User::factory()->create());
        $rematricula->update(['status' => StatusRematricula::DadosConfirmados]);

        return app(RematriculaService::class)->efetivar($rematricula, $destino->id)->contrato()->firstOrFail();
    }

    private function turmaDestino(Serie $serie): Turma
    {
        return Turma::create(['nome' => $serie->nome.' 2027', 'serie_id' => $serie->id, 'turno_id' => $this->turno->id, 'periodo_letivo_id' => $this->destino->id, 'status' => 'planejada', 'vagas_maximas' => 30]);
    }

    public function test_aplicado_o_preco_da_serie_entra_no_novo_contrato_da_rematricula(): void
    {
        $ana = $this->aluno($this->turmaQuinto, 12000, 'Ana Lima');
        $this->aluno($this->turmaQuinto, 13000, 'Bruno Dias');
        $user = User::factory()->create();

        $cenario = $this->cenario();
        // Só o 5º Ano está no cenário; uma série sem preço próprio continua com o valor único da campanha.
        $this->servico()->aplicar($this->servico()->aprovar($cenario, $user), $user);

        $this->assertSame(1, PeriodoRematriculaPreco::count());
        $this->assertSame(13312.5, $this->campanha->valorTotalParaSerie($this->quinto->id));
        $this->assertSame(1000.0, $this->campanha->valorTotalParaSerie($this->sexto->id));

        $contrato = $this->rematricular($ana, $this->turmaDestino($this->quinto));
        $this->assertSame(13312.5, (float) $contrato->valor_total);
    }

    public function test_campanha_sem_reajuste_mantem_o_valor_unico(): void
    {
        $ana = $this->aluno($this->turmaQuinto, 12000, 'Ana Lima');

        $contrato = $this->rematricular($ana, $this->turmaDestino($this->quinto));

        $this->assertSame(1000.0, (float) $contrato->valor_total);
    }

    public function test_nao_aplica_nem_desfaz_depois_de_rematricula_efetivada(): void
    {
        $ana = $this->aluno($this->turmaQuinto, 12000, 'Ana Lima');
        $bruno = $this->aluno($this->turmaQuinto, 13000, 'Bruno Dias');
        $user = User::factory()->create();
        $cenario = $this->servico()->aprovar($this->cenario(), $user);

        $this->rematricular($bruno, $this->turmaDestino($this->quinto));

        try {
            $this->servico()->aplicar($cenario, $user);
            $this->fail('Já há rematrícula efetivada.');
        } catch (ReajusteMensalidadeException $e) {
            $this->assertStringContainsString('já foram efetivadas', $e->getMessage());
        }

        $this->assertSame(0, PeriodoRematriculaPreco::count());
        $this->assertNotNull($ana);
    }

    public function test_desfazer_devolve_a_campanha_ao_valor_unico(): void
    {
        $this->base();
        $user = User::factory()->create();
        $cenario = $this->servico()->aplicar($this->servico()->aprovar($this->cenario(), $user), $user);

        $desfeito = $this->servico()->desfazer($cenario, $user);

        $this->assertSame(StatusCenarioReajuste::Aprovado, $desfeito->status);
        $this->assertSame(0, PeriodoRematriculaPreco::count());
        $this->assertSame(1000.0, $this->campanha->valorTotalParaSerie($this->quinto->id));
    }

    public function test_so_um_cenario_aplicado_por_campanha(): void
    {
        $this->base();
        $user = User::factory()->create();
        $this->servico()->aplicar($this->servico()->aprovar($this->cenario(), $user), $user);
        $outro = $this->servico()->aprovar($this->cenario(8.0), $user);

        $this->expectException(ReajusteMensalidadeException::class);
        $this->expectExceptionMessage('Desfaça-o antes');
        $this->servico()->aplicar($outro, $user);
    }

    // ─── Projeção e alertas ──────────────────────────────────────────

    public function test_projecao_de_receita_considera_alunos_e_bolsas(): void
    {
        $this->base();

        $projecao = $this->servico()->projecao($this->cenario());

        // 2 × 12.500 + 9.000 = 34.000 → 2 × 13.312,50 + 9.585 = 36.210
        $this->assertSame(34000.0, $projecao['receita_atual']);
        $this->assertSame(36210.0, $projecao['receita_nova']);
        $this->assertSame(2210.0, $projecao['diferenca']);
        $this->assertSame(6.5, $projecao['variacao_percentual']);
        $this->assertSame(3, $projecao['alunos']);
        $this->assertSame(36210.0, $projecao['receita_liquida_nova']);
    }

    private function planilha(string $status, float $adotado): PlanilhaLeiMensalidade
    {
        return PlanilhaLeiMensalidade::create([
            'ano_base' => 2026,
            'ano_letivo_destino' => 2027,
            'titulo' => 'Planilha 2027',
            'percentual_reajuste_adotado' => $adotado,
            'status' => $status,
            'responsavel_user_id' => User::factory()->create()->id,
        ]);
    }

    public function test_alertas_de_planilha_e_de_percentual_acima_do_calculado(): void
    {
        $this->base();

        $semPlanilha = $this->servico()->alertas($this->cenario());
        $this->assertStringContainsString('Nenhuma planilha', implode(' ', $semPlanilha));

        $naoHomologada = $this->servico()->alertas($this->cenario(6.5, planilha: $this->planilha(StatusPlanilhaLei::Rascunho->value, 5.0)));
        $texto = implode(' ', $naoHomologada);
        $this->assertStringContainsString('ainda não foi homologada', $texto);
        $this->assertStringContainsString('maior que o da planilha', $texto);

        $ok = $this->servico()->alertas($this->cenario(5.0, planilha: $this->planilha(StatusPlanilhaLei::Homologada->value, 5.0)));
        $this->assertStringNotContainsString('planilha', implode(' ', $ok));
    }

    /**
     * Publica a lista de material das duas séries no período de destino, para o cenário "tudo em dia" não ter o alerta de lista.
     */
    private function publicarListasDeMaterial(): void
    {
        $material = app(ListaMaterialService::class);

        foreach ([$this->quinto, $this->sexto] as $serie) {
            $lista = ListaMaterialEscolar::create(['serie_id' => $serie->id, 'periodo_letivo_id' => $this->destino->id]);
            $material->salvarItem($lista, ['descricao' => 'Caderno brochura', 'quantidade' => 1]);
            $material->publicar($lista, null);
        }
    }

    public function test_alerta_de_prazo_legal_de_divulgacao(): void
    {
        $this->base();
        $this->publicarListasDeMaterial();
        $cenario = $this->cenario(5.0, planilha: $this->planilha(StatusPlanilhaLei::Homologada->value, 5.0));

        // Fim da campanha em 31/01/2027: o limite de divulgação é 17/12/2026; hoje (21/10) ainda dá tempo.
        $this->assertSame([], $this->servico()->alertas($cenario));

        $this->campanha->update(['data_fim' => '2026-11-20']); // limite em 06/10/2026: já passou
        $alertas = $this->servico()->alertas($cenario->fresh());
        $this->assertStringContainsString('Lei nº 9.870/1999', implode(' ', $alertas));
    }

    public function test_comunicado_so_depois_de_aprovar_e_sua_data_vale_para_o_prazo(): void
    {
        $this->base();
        $user = User::factory()->create();
        $cenario = $this->cenario(5.0, planilha: $this->planilha(StatusPlanilhaLei::Homologada->value, 5.0));

        try {
            $this->servico()->registrarComunicado($cenario, $user);
            $this->fail('Simulação não se comunica.');
        } catch (ReajusteMensalidadeException) {
            // esperado
        }

        $comunicado = $this->servico()->registrarComunicado($this->servico()->aprovar($cenario, $user), $user);
        $this->assertSame('2026-10-21', $comunicado->comunicado_em->toDateString());

        // Depois do limite (fim em 20/11 → limite 06/10), a data registrada é a que conta.
        $this->campanha->update(['data_fim' => '2026-11-20']);
        $this->assertStringContainsString('foi feita com', implode(' ', $this->servico()->alertas($comunicado->fresh())));
    }

    // ─── Comunicação ─────────────────────────────────────────────────

    public function test_lista_de_comunicacao_traz_responsavel_aluno_e_valores(): void
    {
        $this->base();

        $linhas = $this->servico()->destinatarios($this->cenario());

        $this->assertCount(3, $linhas);
        $carla = $linhas->firstWhere('responsavel', 'Carla Lima');
        $this->assertSame('Ana Lima', $carla['aluno']);
        $this->assertSame('5º Ano', $carla['serie']);
        $this->assertSame(12500.0, $carla['valor_atual']);
        $this->assertSame(13312.5, $carla['valor_novo']);
        $this->assertSame(6.5, $carla['variacao_percentual']);
        $this->assertSame('carla.lima@exemplo.com', $carla['email']);
    }

    public function test_csv_tem_bom_ponto_e_virgula_e_bloqueia_injecao_de_formula(): void
    {
        $this->aluno($this->turmaQuinto, 12000, 'Ana Lima', responsavel: '=HYPERLINK("http://x")');

        $csv = $this->servico()->csvDeComunicacao($this->cenario());

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Responsável;E-mail;Telefone;Aluno;Série;"Valor atual";"Valor novo";"Variação (%)"', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString('12.780,00', $csv); // 12.000 + 6,5%
    }

    // ─── Telas ───────────────────────────────────────────────────────

    private function admin(array $permissoes = ['ViewAny:CenarioReajuste', 'View:CenarioReajuste', 'Create:CenarioReajuste', 'Update:CenarioReajuste', 'Delete:CenarioReajuste', 'Aprovar:CenarioReajuste', 'Aplicar:CenarioReajuste', 'Comunicar:CenarioReajuste'], string $papel = 'admin'): User
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

    public function test_tela_cria_a_simulacao_e_abre_a_edicao(): void
    {
        $this->base();
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(CreateCenarioReajuste::class)
            ->assertActionExists('ajuda')
            ->fillForm(['periodo_rematricula_id' => $this->campanha->id, 'nome' => 'Reajuste 2027', 'percentual_padrao' => 6.5, 'arredondamento' => 'centavos'])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $cenario = CenarioReajuste::first();
        $this->assertSame(2, $cenario->itens()->count());
        $this->assertSame($admin->id, $cenario->criado_por_user_id);
    }

    public function test_tela_avisa_quando_nao_ha_contratos_para_simular(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(CreateCenarioReajuste::class)
            ->fillForm(['periodo_rematricula_id' => $this->campanha->id, 'nome' => 'Reajuste 2027', 'percentual_padrao' => 6.5, 'arredondamento' => 'centavos'])
            ->call('create')
            ->assertNotified('Não foi possível simular');

        $this->assertSame(0, CenarioReajuste::count());
    }

    public function test_tela_ajusta_serie_aprova_e_aplica(): void
    {
        $this->base();
        $admin = $this->admin();
        $cenario = $this->cenario();

        Livewire::actingAs($admin)
            ->test(ItensRelationManager::class, ['ownerRecord' => $cenario, 'pageClass' => EditCenarioReajuste::class])
            ->callTableAction('ajustar', $this->item($cenario, $this->sexto), ['percentual' => 10, 'valor_atual' => 9000])
            ->assertNotified('Série recalculada');
        $this->assertSame('9900.00', (string) $this->item($cenario, $this->sexto)->valor_novo);

        Livewire::actingAs($admin)
            ->test(EditCenarioReajuste::class, ['record' => $cenario->getRouteKey()])
            ->assertSee('Receita anual com reajuste')
            ->callAction('aprovar')
            ->assertNotified('Cenário aprovado');

        Livewire::actingAs($admin)
            ->test(ViewCenarioReajuste::class, ['record' => $cenario->getRouteKey()])
            ->assertActionHidden('aprovar')
            ->callAction('aplicar')
            ->assertNotified('Preços aplicados na campanha');

        $this->assertSame(StatusCenarioReajuste::Aplicado, $cenario->fresh()->status);
        $this->assertSame(2, PeriodoRematriculaPreco::count());
    }

    public function test_tela_baixa_a_lista_de_comunicacao_e_registra_a_exportacao(): void
    {
        $this->base();
        $admin = $this->admin();
        $cenario = $this->servico()->aprovar($this->cenario(), $admin);
        Activity::query()->delete();

        Livewire::actingAs($admin)
            ->test(ViewCenarioReajuste::class, ['record' => $cenario->getRouteKey()])
            ->callAction('baixarLista')
            ->assertFileDownloaded('reajuste-reajuste-2027.csv');

        $this->assertSame(1, Activity::where('description', 'Lista de comunicação do reajuste exportada.')->count());
    }

    public function test_secretaria_nao_acessa_o_reajuste(): void
    {
        $secretaria = $this->admin([], 'secretaria');

        Livewire::actingAs($secretaria)->test(ListCenarioReajustes::class)->assertForbidden();
    }

    public function test_alerta_as_series_sem_lista_de_material_publicada_no_periodo_de_destino(): void
    {
        $this->base();
        $cenario = $this->cenario();
        $material = app(ListaMaterialService::class);

        $this->assertStringContainsString('lista de material escolar publicada para: 5º Ano, 6º Ano.', implode(' ', $this->servico()->alertas($cenario)));

        // Rascunho e lista de outro período não contam; só a publicada da série no período de destino.
        $rascunho = ListaMaterialEscolar::create(['serie_id' => $this->sexto->id, 'periodo_letivo_id' => $this->destino->id]);
        $material->salvarItem($rascunho, ['descricao' => 'Caderno brochura', 'quantidade' => 1]);
        $doAnoPassado = ListaMaterialEscolar::create(['serie_id' => $this->sexto->id, 'periodo_letivo_id' => $this->origem->id]);
        $material->salvarItem($doAnoPassado, ['descricao' => 'Caderno brochura', 'quantidade' => 1]);
        $material->publicar($doAnoPassado, null);

        $publicada = ListaMaterialEscolar::create(['serie_id' => $this->quinto->id, 'periodo_letivo_id' => $this->destino->id]);
        $material->salvarItem($publicada, ['descricao' => 'Caderno brochura', 'quantidade' => 1]);
        $material->publicar($publicada, null);

        $alertas = implode(' ', $this->servico()->alertas($cenario->fresh()));

        $this->assertStringContainsString('lista de material escolar publicada para: 6º Ano.', $alertas);

        $material->publicar($rascunho, null);

        $this->assertStringNotContainsString('lista de material', implode(' ', $this->servico()->alertas($cenario->fresh())));
    }
}
