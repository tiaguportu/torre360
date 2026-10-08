<?php

namespace Tests\Feature;

use App\Enums\StatusRematricula;
use App\Enums\StatusTurma;
use App\Filament\Portal\Pages\Rematricula as PaginaRematricula;
use App\Models\Contrato;
use App\Models\Curso;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\PeriodoRematricula;
use App\Models\Pessoa;
use App\Models\Rematricula;
use App\Models\Serie;
use App\Models\TemplateContrato;
use App\Models\Turma;
use App\Models\Turno;
use App\Models\Unidade;
use App\Models\User;
use App\Services\AssinafyService;
use App\Services\RematriculaService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RematriculaAssinaturaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // O .env local pode ter credenciais reais do Assinafy: o webhook não pode consultar a API de verdade.
        Http::preventStrayRequests();
        config(['services.assinafy.key' => '']);
    }

    /**
     * @return array{rematricula: Rematricula}
     */
    private function prepararRematriculaComTemplate(): array
    {
        $origem = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);
        $destino = PeriodoLetivo::create(['nome' => '2027', 'data_inicio' => '2027-02-01', 'data_fim' => '2027-12-15']);

        $templateContrato = TemplateContrato::create([
            'nome' => 'Contrato Padrão 2027',
            'conteudo' => '<p>Termos.</p>',
            'is_padrao' => true,
        ]);

        $periodo = PeriodoRematricula::create([
            'nome' => 'Rematrícula 2027',
            'periodo_letivo_origem_id' => $origem->id,
            'periodo_letivo_destino_id' => $destino->id,
            'template_contrato_id' => $templateContrato->id,
            'valor_taxa' => 1200.00,
            'quantidade_parcelas_padrao' => 12,
            'valor_entrada_padrao' => 0,
            'data_inicio' => now()->subDays(2)->toDateString(),
            'data_fim' => now()->addDays(15)->toDateString(),
            'is_ativo' => true,
        ]);

        $aluno = Pessoa::create(['nome' => 'Aluno Assinatura Teste', 'cpf' => '11122233344']);
        $turmaOrigem = Turma::create(['nome' => '4º Ano', 'periodo_letivo_id' => $origem->id]);
        $matriculaOrigem = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turmaOrigem->id,
            'situacao' => 'ativa',
        ]);
        $turmaDestino = Turma::create(['nome' => '5º Ano', 'periodo_letivo_id' => $destino->id]);

        $user = User::factory()->create();

        $service = app(RematriculaService::class);
        $rematricula = $service->iniciarOuObter($matriculaOrigem, $periodo, $user);
        $rematricula->update(['turma_destino_id' => $turmaDestino->id, 'status' => StatusRematricula::DadosConfirmados]);

        return compact('rematricula');
    }

    public function test_efetivar_com_assinafy_configurado_fica_aguardando_assinatura(): void
    {
        $this->mock(AssinafyService::class, function ($mock) {
            $mock->shouldReceive('enviarContrato')->once()->andReturn(['success' => true, 'message' => 'Enviado']);
        });

        ['rematricula' => $rematricula] = $this->prepararRematriculaComTemplate();

        app(RematriculaService::class)->efetivar($rematricula);

        $rematricula->refresh();
        $this->assertEquals(StatusRematricula::AguardandoAssinatura, $rematricula->status);
        $this->assertNull($rematricula->data_confirmacao);
    }

    public function test_webhook_assinafy_confirma_rematricula_quando_contrato_e_assinado(): void
    {
        ['rematricula' => $rematricula] = $this->prepararRematriculaComTemplate();

        app(RematriculaService::class)->efetivar($rematricula);
        $rematricula->refresh();
        $contrato = $rematricula->contrato;

        // Simula o envio ter sido feito (campo normalmente preenchido por enviarContrato())
        $contrato->update(['assinafy_id' => 'DOC-TESTE-123']);

        $processado = app(AssinafyService::class)->handleWebhook([
            'event' => 'document_ready',
            'object' => ['id' => 'DOC-TESTE-123'],
        ]);

        $this->assertTrue($processado);

        $rematricula->refresh();
        $this->assertEquals(StatusRematricula::Confirmada, $rematricula->status);
        $this->assertNotNull($rematricula->data_confirmacao);
    }

    public function test_webhook_nao_confirma_rematricula_ja_confirmada_de_novo(): void
    {
        ['rematricula' => $rematricula] = $this->prepararRematriculaComTemplate();
        app(RematriculaService::class)->efetivar($rematricula);
        $rematricula->refresh();
        $contrato = $rematricula->contrato;
        $contrato->update(['assinafy_id' => 'DOC-TESTE-456']);

        app(AssinafyService::class)->handleWebhook(['event' => 'document_ready', 'object' => ['id' => 'DOC-TESTE-456']]);
        $primeiraConfirmacao = $rematricula->fresh()->data_confirmacao;

        app(AssinafyService::class)->handleWebhook(['event' => 'document_ready', 'object' => ['id' => 'DOC-TESTE-456']]);
        $segundaConfirmacao = $rematricula->fresh()->data_confirmacao;

        $this->assertTrue($primeiraConfirmacao->equalTo($segundaConfirmacao));
    }

    // --- Idempotência de efetivar() ---

    public function test_efetivar_repetido_nao_duplica_matricula_contrato_faturas_nem_envio_ao_assinafy(): void
    {
        // once(): a segunda chamada de efetivar() não pode enviar outro documento ao Assinafy
        $this->mock(AssinafyService::class, function ($mock) {
            $mock->shouldReceive('enviarContrato')->once()->andReturn(['success' => true]);
        });

        ['rematricula' => $rematricula] = $this->prepararRematriculaComTemplate();
        $service = app(RematriculaService::class);

        $primeira = $service->efetivar($rematricula);
        $segunda = $service->efetivar($rematricula);

        $this->assertSame($primeira->id, $segunda->id);
        $this->assertSame(1, Matricula::doPeriodo($primeira->periodo_letivo_id)->count());
        $this->assertSame(1, Contrato::where('matricula_id', $primeira->id)->count());
        $this->assertSame(12, $rematricula->fresh()->contrato->faturas()->count());
        // Segue aguardando a assinatura: repetir a chamada não "volta" o andamento
        $this->assertEquals(StatusRematricula::AguardandoAssinatura, $rematricula->fresh()->status);
    }

    public function test_efetivar_nao_deixa_registros_pela_metade_quando_a_geracao_de_faturas_falha(): void
    {
        // once(): o envio só acontece na nova tentativa que dá certo, nunca na que falhou
        $this->mock(AssinafyService::class, function ($mock) {
            $mock->shouldReceive('enviarContrato')->once()->andReturn(['success' => true]);
        });

        ['rematricula' => $rematricula] = $this->prepararRematriculaComTemplate();
        $periodo = $rematricula->periodoRematricula;
        $service = app(RematriculaService::class);

        // Entrada maior que o valor do contrato: gerar() lança exceção depois de a matrícula e o contrato existirem
        $periodo->update(['valor_entrada_padrao' => 5000]);

        try {
            $service->efetivar($rematricula);
            $this->fail('Era esperada uma InvalidArgumentException.');
        } catch (\InvalidArgumentException) {
            // esperado
        }

        $this->assertSame(0, Matricula::doPeriodo($periodo->periodo_letivo_destino_id)->count());
        $this->assertSame(0, Contrato::count());
        $this->assertNull($rematricula->fresh()->nova_matricula_id);

        // Corrigida a campanha, uma nova tentativa gera tudo uma única vez
        $periodo->update(['valor_entrada_padrao' => 0]);
        $service->efetivar($rematricula);

        $this->assertSame(1, Matricula::doPeriodo($periodo->periodo_letivo_destino_id)->count());
        $this->assertSame(1, Contrato::count());
        $this->assertEquals(StatusRematricula::AguardandoAssinatura, $rematricula->fresh()->status);
    }

    // --- Vencimentos das faturas x data da assinatura ---

    public function test_faturas_partem_do_dia_da_rematricula_e_a_assinatura_nao_as_desalinha(): void
    {
        // Segunda-feira: 5 dias úteis depois é a segunda seguinte (12/10)
        $this->travelTo(Carbon::parse('2026-10-05 10:00:00'));

        ['rematricula' => $rematricula] = $this->prepararRematriculaComTemplate();
        app(RematriculaService::class)->efetivar($rematricula);

        $contrato = $rematricula->fresh()->contrato;
        $vencimentosAntes = $contrato->faturas()->orderBy('vencimento')->pluck('vencimento')->map->toDateString()->all();

        // Ainda não foi aceito/assinado: não há data de aceite
        $this->assertNull($contrato->data_aceite);
        $this->assertSame('2026-10-12', $vencimentosAntes[0]);

        // A família assina duas semanas depois
        $this->travelTo(Carbon::parse('2026-10-20 15:00:00'));
        $contrato->update(['assinafy_id' => 'DOC-TESTE-789']);
        app(AssinafyService::class)->handleWebhook(['event' => 'document_ready', 'object' => ['id' => 'DOC-TESTE-789']]);

        $contrato->refresh();
        $this->assertTrue($contrato->data_aceite->equalTo(Carbon::parse('2026-10-20 15:00:00')));
        $this->assertSame(
            $vencimentosAntes,
            $contrato->faturas()->orderBy('vencimento')->pluck('vencimento')->map->toDateString()->all(),
            'A assinatura não pode mexer nos vencimentos já gerados.'
        );
    }

    // --- Portal: ação "Realizar Rematrícula" ---

    /**
     * @return array{user: User, matricula: Matricula, serie: Serie, turno: Turno, turmaDestino: Turma, campanha: PeriodoRematricula}
     */
    private function prepararPortal(bool $comTemplate = true): array
    {
        Role::firstOrCreate(['name' => 'responsavel', 'guard_name' => 'web']);
        Filament::setCurrentPanel(Filament::getPanel('portal'));

        $origem = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);
        $destino = PeriodoLetivo::create(['nome' => '2027', 'data_inicio' => '2027-02-01', 'data_fim' => '2027-12-15']);

        $campanha = PeriodoRematricula::create([
            'nome' => 'Rematrícula 2027',
            'periodo_letivo_origem_id' => $origem->id,
            'periodo_letivo_destino_id' => $destino->id,
            'template_contrato_id' => $comTemplate
                ? TemplateContrato::create(['nome' => 'Contrato 2027', 'conteudo' => '<p>Termos.</p>', 'is_padrao' => true])->id
                : null,
            'valor_taxa' => 1200.00,
            'quantidade_parcelas_padrao' => 12,
            'valor_entrada_padrao' => 0,
            'data_inicio' => now()->subDays(2)->toDateString(),
            'data_fim' => now()->addDays(15)->toDateString(),
            'is_ativo' => true,
        ]);

        $responsavel = Pessoa::create(['nome' => 'Responsável Portal Teste', 'cpf' => '99988877766']);
        $user = User::factory()->create(['activated_at' => now()]);
        $responsavel->users()->save($user);
        $user->assignRole('responsavel');

        $aluno = Pessoa::create(['nome' => 'Aluno Portal Teste', 'cpf' => '11122233355']);
        $responsavel->alunos()->attach($aluno->id);

        $matricula = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => Turma::create(['nome' => '4º Ano', 'periodo_letivo_id' => $origem->id])->id,
            'situacao' => 'ativa',
        ]);

        $unidade = Unidade::create(['nome' => 'Unidade Portal Teste']);
        $curso = Curso::create(['nome_externo' => 'Fundamental', 'nome_interno' => 'Fundamental', 'unidade_id' => $unidade->id]);
        $serie = Serie::create(['nome' => '5º Ano', 'curso_id' => $curso->id, 'sistema_avaliacao' => 'Nota']);
        $turno = Turno::create(['nome' => 'Manhã', 'hora_inicio' => '07:00:00', 'hora_fim' => '12:00:00']);

        // Turma já cadastrada para o período de destino (a secretaria a escolhe ao efetivar).
        $turmaDestino = Turma::create([
            'nome' => '5º Ano A',
            'serie_id' => $serie->id,
            'turno_id' => $turno->id,
            'periodo_letivo_id' => $destino->id,
            'status' => StatusTurma::Planejada,
            'vagas_maximas' => 25,
        ]);

        return compact('user', 'matricula', 'serie', 'turno', 'turmaDestino', 'campanha');
    }

    /**
     * @return array<string, int>
     */
    private function dadosDoFormulario(Serie $serie, Turno $turno): array
    {
        return ['serie_destino_id' => $serie->id, 'turno_pretendido_id' => $turno->id];
    }

    public function test_portal_so_registra_a_intencao_e_nao_cria_matricula_nem_contrato(): void
    {
        // A família não escolhe turma: nada de matrícula, contrato ou envio ao Assinafy neste passo.
        $this->mock(AssinafyService::class, function ($mock) {
            $mock->shouldNotReceive('enviarContrato');
        });
        ['user' => $user, 'matricula' => $matricula, 'serie' => $serie, 'turno' => $turno] = $this->prepararPortal();

        Livewire::actingAs($user)
            ->test(PaginaRematricula::class)
            ->callTableAction('iniciar_rematricula', $matricula, data: $this->dadosDoFormulario($serie, $turno))
            ->assertNotified('Preferências registradas!')
            ->assertNotNotified('Rematrícula Confirmada!');

        $rematricula = Rematricula::firstOrFail();
        $this->assertEquals(StatusRematricula::DadosConfirmados, $rematricula->status);
        $this->assertSame($serie->id, $rematricula->serie_destino_id);
        $this->assertSame($turno->id, $rematricula->turno_pretendido_id);
        $this->assertNull($rematricula->turma_destino_id);
        $this->assertNull($rematricula->nova_matricula_id);
        $this->assertSame(1, Matricula::count(), 'Só a matrícula de origem existe.');
        $this->assertSame(0, Contrato::count());
    }

    public function test_portal_permite_atualizar_as_preferencias_enquanto_a_secretaria_nao_efetivar(): void
    {
        $this->mock(AssinafyService::class, fn ($mock) => $mock->shouldNotReceive('enviarContrato'));
        ['user' => $user, 'matricula' => $matricula, 'serie' => $serie, 'turno' => $turno] = $this->prepararPortal();
        $outroTurno = Turno::create(['nome' => 'Tarde', 'hora_inicio' => '13:00:00', 'hora_fim' => '17:00:00']);

        $pagina = Livewire::actingAs($user)->test(PaginaRematricula::class);
        $pagina->callTableAction('iniciar_rematricula', $matricula, data: $this->dadosDoFormulario($serie, $turno));
        $pagina->assertTableActionVisible('iniciar_rematricula', $matricula)
            ->callTableAction('iniciar_rematricula', $matricula, data: $this->dadosDoFormulario($serie, $outroTurno));

        $this->assertSame(1, Rematricula::count());
        $this->assertSame($outroTurno->id, Rematricula::first()->turno_pretendido_id);
    }

    public function test_secretaria_efetiva_a_intencao_da_familia_numa_turma_e_o_portal_esconde_a_acao(): void
    {
        $this->mock(AssinafyService::class, function ($mock) {
            $mock->shouldReceive('enviarContrato')->once()->andReturn(['success' => true]);
        });
        ['user' => $user, 'matricula' => $matricula, 'serie' => $serie, 'turno' => $turno, 'turmaDestino' => $turmaDestino] = $this->prepararPortal();

        Livewire::actingAs($user)
            ->test(PaginaRematricula::class)
            ->assertTableActionVisible('iniciar_rematricula', $matricula)
            ->callTableAction('iniciar_rematricula', $matricula, data: $this->dadosDoFormulario($serie, $turno));

        $novaMatricula = app(RematriculaService::class)->efetivar(Rematricula::firstOrFail(), $turmaDestino->id);

        $this->assertSame($turmaDestino->id, $novaMatricula->turma_id);
        $this->assertEquals(StatusRematricula::AguardandoAssinatura, Rematricula::first()->status);

        Livewire::actingAs($user)
            ->test(PaginaRematricula::class)
            ->assertTableActionHidden('iniciar_rematricula', $matricula);
    }

    public function test_efetivar_sem_contrato_na_campanha_confirma_direto(): void
    {
        ['user' => $user, 'matricula' => $matricula, 'serie' => $serie, 'turno' => $turno, 'turmaDestino' => $turmaDestino] = $this->prepararPortal(comTemplate: false);

        Livewire::actingAs($user)
            ->test(PaginaRematricula::class)
            ->callTableAction('iniciar_rematricula', $matricula, data: $this->dadosDoFormulario($serie, $turno));

        app(RematriculaService::class)->efetivar(Rematricula::firstOrFail(), $turmaDestino->id);

        $this->assertEquals(StatusRematricula::Confirmada, Rematricula::first()->status);
    }

    public function test_portal_nao_reexecuta_a_acao_por_requisicao_direta_depois_de_efetivada(): void
    {
        // once(): uma segunda execução enviaria outro documento ao Assinafy
        $this->mock(AssinafyService::class, function ($mock) {
            $mock->shouldReceive('enviarContrato')->once()->andReturn(['success' => true]);
        });
        ['user' => $user, 'matricula' => $matricula, 'serie' => $serie, 'turno' => $turno, 'turmaDestino' => $turmaDestino] = $this->prepararPortal();
        $dados = $this->dadosDoFormulario($serie, $turno);

        $pagina = Livewire::actingAs($user)->test(PaginaRematricula::class);

        // callTableAction() recusa ação escondida; mount + callMounted reproduzem uma chamada direta ao servidor.
        // Controle: com a ação visível esse caminho registra a intenção normalmente.
        $pagina->mountTableAction('iniciar_rematricula', $matricula)->setTableActionData($dados)->callMountedTableAction();
        $this->assertSame(1, Rematricula::count());
        $this->assertEquals(StatusRematricula::DadosConfirmados, Rematricula::first()->status);

        app(RematriculaService::class)->efetivar(Rematricula::firstOrFail(), $turmaDestino->id);

        // Depois de efetivada o servidor não monta mais a ação (o Filament também aplica visible() ali)
        $pagina->mountTableAction('iniciar_rematricula', $matricula)->setTableActionData($dados)->callMountedTableAction();

        $this->assertSame(1, Rematricula::count());
        $this->assertSame(1, Contrato::count());
        $this->assertEquals(StatusRematricula::AguardandoAssinatura, Rematricula::first()->status);
    }
}
