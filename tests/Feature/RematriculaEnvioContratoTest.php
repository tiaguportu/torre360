<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SituacaoMatricula;
use App\Enums\StatusRematricula;
use App\Filament\Resources\Rematriculas\Pages\ListRematriculas;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\PeriodoRematricula;
use App\Models\Pessoa;
use App\Models\Rematricula;
use App\Models\TemplateContrato;
use App\Models\Turma;
use App\Models\User;
use App\Services\AssinafyService;
use App\Services\RematriculaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * O que acontece com a rematrícula quando o contrato é (ou não) enviado para assinatura: o status
 * acompanha o envio mesmo quando ele é feito depois, e a secretaria é avisada pelo estado real.
 */
class RematriculaEnvioContratoTest extends TestCase
{
    use RefreshDatabase;

    private PeriodoLetivo $origem;

    private PeriodoRematricula $campanha;

    private Turma $turmaDestino;

    protected function setUp(): void
    {
        parent::setUp();

        // O .env local pode ter credenciais reais (de produção) do Assinafy: nada pode sair para a API de verdade.
        Http::preventStrayRequests();
        config(['services.assinafy.key' => '']);

        $this->origem = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);
        $destino = PeriodoLetivo::create(['nome' => '2027', 'data_inicio' => '2027-02-01', 'data_fim' => '2027-12-15']);

        $this->campanha = PeriodoRematricula::create([
            'nome' => 'Rematrícula 2027',
            'periodo_letivo_origem_id' => $this->origem->id,
            'periodo_letivo_destino_id' => $destino->id,
            'template_contrato_id' => TemplateContrato::create(['nome' => 'Contrato 2027', 'conteudo' => '<p>Termos.</p>', 'is_padrao' => true])->id,
            'valor_taxa' => 1200.00,
            'quantidade_parcelas_padrao' => 12,
            'valor_entrada_padrao' => 0,
            'data_inicio' => now()->subDay()->toDateString(),
            'data_fim' => now()->addDays(10)->toDateString(),
            'is_ativo' => true,
        ]);

        $this->turmaDestino = Turma::create([
            'nome' => '5º Ano A',
            'periodo_letivo_id' => $destino->id,
            'status' => 'planejada',
            'vagas_maximas' => 30,
        ]);
    }

    private function rematricula(string $nomeAluno = 'Aluno Envio'): Rematricula
    {
        $aluno = Pessoa::create(['nome' => $nomeAluno]);
        $matricula = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => Turma::create(['nome' => '4º Ano '.$nomeAluno, 'periodo_letivo_id' => $this->origem->id])->id,
            'situacao' => SituacaoMatricula::ATIVA,
        ]);

        $rematricula = app(RematriculaService::class)->iniciarOuObter($matricula, $this->campanha, User::factory()->create());
        $rematricula->update(['status' => StatusRematricula::DadosConfirmados]);

        return $rematricula;
    }

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::factory()->create(['activated_at' => now()]);
        $admin->assignRole('super_admin');
        session(['active_role' => 'super_admin']);

        return $admin;
    }

    /** O envio ao Assinafy dá certo (o serviço é substituído; nenhuma chamada HTTP acontece). */
    private function enviosBemSucedidos(): void
    {
        $this->mock(AssinafyService::class, fn ($mock) => $mock->shouldReceive('enviarContrato')->andReturn(['success' => true]));
    }

    // --- Status acompanha o envio, mesmo feito depois ---

    public function test_envio_feito_depois_da_falha_move_a_rematricula_para_aguardando_assinatura(): void
    {
        $rematricula = $this->rematricula();

        // Sem credenciais do Assinafy (zeradas no setUp) o primeiro envio falha dentro de efetivar()
        app(RematriculaService::class)->efetivar($rematricula, $this->turmaDestino->id);

        $rematricula->refresh();
        $this->assertEquals(StatusRematricula::DadosConfirmados, $rematricula->status);
        $contrato = $rematricula->contrato;

        // Depois a secretaria (ou a família) envia pelo Contrato. O documento já existe no Assinafy e o
        // envio reaproveita o link de assinatura do responsável.
        config([
            'services.assinafy.key' => 'CHAVE-DE-TESTE',
            'services.assinafy.account_id' => 'ACC-TESTE',
            'services.assinafy.url' => 'https://assinafy.teste/v1',
        ]);
        $responsavel = Pessoa::create(['nome' => 'Responsável Envio', 'email' => 'responsavel@example.com']);
        $contrato->responsaveisFinanceiros()->create(['pessoa_id' => $responsavel->id, 'percentual' => 100]);
        $contrato->update(['assinafy_id' => 'DOC-REENVIO']);

        Http::fake([
            'assinafy.teste/*' => Http::response(['data' => [
                'status' => 'pending_signature',
                'assignment' => ['signing_urls' => [['url' => 'https://assinafy.teste/sign?email=responsavel%40example.com', 'signer_id' => 'S1']]],
            ]]),
        ]);

        $resultado = (new AssinafyService)->enviarContrato($contrato->fresh());

        $this->assertTrue($resultado['success']);
        $this->assertEquals(StatusRematricula::AguardandoAssinatura, $rematricula->fresh()->status);
    }

    public function test_marcar_aguardando_assinatura_so_promove_dados_confirmados(): void
    {
        $rematricula = $this->rematricula();
        app(RematriculaService::class)->efetivar($rematricula, $this->turmaDestino->id);
        $contrato = $rematricula->fresh()->contrato;

        // Qualquer outro estado fica como está (em especial, nunca desfaz uma confirmação do webhook)
        foreach ([StatusRematricula::Iniciada, StatusRematricula::AguardandoAssinatura, StatusRematricula::Confirmada, StatusRematricula::Cancelada] as $status) {
            $rematricula->update(['status' => $status]);
            $contrato->marcarRematriculaAguardandoAssinatura();

            $this->assertEquals($status, $rematricula->fresh()->status, "Não deveria mexer em {$status->value}.");
        }

        $rematricula->update(['status' => StatusRematricula::DadosConfirmados]);
        $contrato->marcarRematriculaAguardandoAssinatura();

        $this->assertEquals(StatusRematricula::AguardandoAssinatura, $rematricula->fresh()->status);
    }

    public function test_envio_sem_credenciais_nao_promove_a_rematricula(): void
    {
        $rematricula = $this->rematricula();
        app(RematriculaService::class)->efetivar($rematricula, $this->turmaDestino->id);

        $resultado = (new AssinafyService)->enviarContrato($rematricula->fresh()->contrato);

        $this->assertFalse($resultado['success']);
        $this->assertEquals(StatusRematricula::DadosConfirmados, $rematricula->fresh()->status);
    }

    // --- Aviso da secretaria pelo estado real ---

    public function test_secretaria_e_avisada_quando_a_matricula_e_gerada_mas_o_contrato_nao_e_enviado(): void
    {
        $rematricula = $this->rematricula();

        Livewire::actingAs($this->admin())
            ->test(ListRematriculas::class)
            ->callTableAction('efetivar', $rematricula, data: ['turma_id' => $this->turmaDestino->id])
            ->assertNotified('Matrícula gerada, mas o contrato não foi enviado')
            ->assertNotNotified('Rematrícula Efetivada');

        $this->assertNotNull($rematricula->fresh()->nova_matricula_id);
        $this->assertEquals(StatusRematricula::DadosConfirmados, $rematricula->fresh()->status);
    }

    public function test_secretaria_ve_sucesso_quando_o_contrato_foi_enviado(): void
    {
        $this->enviosBemSucedidos();
        $rematricula = $this->rematricula();

        Livewire::actingAs($this->admin())
            ->test(ListRematriculas::class)
            ->callTableAction('efetivar', $rematricula, data: ['turma_id' => $this->turmaDestino->id])
            ->assertNotified('Rematrícula Efetivada')
            ->assertNotNotified('Matrícula gerada, mas o contrato não foi enviado');

        $this->assertEquals(StatusRematricula::AguardandoAssinatura, $rematricula->fresh()->status);
    }

    public function test_efetivacao_em_lote_informa_quantas_ficaram_sem_contrato_enviado(): void
    {
        $r1 = $this->rematricula('Aluno 1');
        $r2 = $this->rematricula('Aluno 2');

        Livewire::actingAs($this->admin())
            ->test(ListRematriculas::class)
            ->callTableBulkAction('efetivarEmLote', [$r1, $r2], data: ['turma_id' => $this->turmaDestino->id])
            ->assertNotified('2 rematrícula(s) efetivada(s), 2 sem contrato enviado');

        $this->assertSame(2, Rematricula::whereNotNull('nova_matricula_id')->count());
    }

    public function test_efetivacao_em_lote_sem_problemas_mantem_o_titulo_simples(): void
    {
        $this->enviosBemSucedidos();
        $r1 = $this->rematricula('Aluno 1');
        $r2 = $this->rematricula('Aluno 2');

        Livewire::actingAs($this->admin())
            ->test(ListRematriculas::class)
            ->callTableBulkAction('efetivarEmLote', [$r1, $r2], data: ['turma_id' => $this->turmaDestino->id])
            ->assertNotified('2 rematrícula(s) efetivada(s)');

        $this->assertSame(2, Rematricula::where('status', StatusRematricula::AguardandoAssinatura)->count());
    }

    // --- "Dados Confirmados" deixa de ser ambíguo na listagem ---

    public function test_status_dados_confirmados_diz_se_aguarda_a_secretaria_ou_se_falta_enviar_o_contrato(): void
    {
        $aguardando = $this->rematricula('Aluno Aguardando');
        $semEnvio = $this->rematricula('Aluno Sem Envio');
        app(RematriculaService::class)->efetivar($semEnvio, $this->turmaDestino->id);

        Livewire::actingAs($this->admin())
            ->test(ListRematriculas::class)
            ->assertTableColumnHasDescription('status', 'Aguardando a secretaria', $aguardando)
            ->assertTableColumnHasDescription('status', 'Contrato não enviado', $semEnvio);
    }

    public function test_status_diferente_de_dados_confirmados_nao_tem_descricao(): void
    {
        $this->enviosBemSucedidos();
        $rematricula = $this->rematricula();
        app(RematriculaService::class)->efetivar($rematricula, $this->turmaDestino->id);

        Livewire::actingAs($this->admin())
            ->test(ListRematriculas::class)
            ->assertTableColumnDoesNotHaveDescription('status', 'Contrato não enviado', $rematricula)
            ->assertTableColumnDoesNotHaveDescription('status', 'Aguardando a secretaria', $rematricula);
    }
}
