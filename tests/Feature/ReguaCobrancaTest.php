<?php

namespace Tests\Feature;

use App\Enums\StatusFatura;
use App\Filament\Resources\ReguaCobrancas\Pages\ListReguaCobrancas;
use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\ItemFatura;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\ReguaCobranca;
use App\Models\ReguaCobrancaLog;
use App\Models\ResponsavelFinanceiro;
use App\Models\Turma;
use App\Models\User;
use App\Services\ReguaCobrancaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReguaCobrancaTest extends TestCase
{
    use RefreshDatabase;

    private ReguaCobrancaService $service;

    private PeriodoLetivo $periodoLetivo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ReguaCobrancaService::class);

        $this->periodoLetivo = PeriodoLetivo::create([
            'nome' => 'Ano Letivo 2026',
            'data_inicio' => '2026-02-01',
            'data_fim' => '2026-12-15',
        ]);

        ReguaCobranca::query()->delete();
    }

    private function criarEstruturaFinanceira(Carbon $vencimento, float $valor = 500.0, string $status = 'pendente'): array
    {
        $aluno = Pessoa::create([
            'nome' => 'Estudante Teste',
            'cpf' => (string) rand(10000000000, 99999999999),
            'data_nascimento' => '2016-04-10',
        ]);

        $responsavel = Pessoa::create([
            'nome' => 'Responsável Financeiro Silva',
            'cpf' => (string) rand(10000000000, 99999999999),
            'email' => 'responsavel@example.com',
            'telefone' => '11999998888',
        ]);

        $matricula = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => Turma::factory()->create(['periodo_letivo_id' => $this->periodoLetivo->id])->id,
            'situacao' => 'ativa',
        ]);

        $contrato = Contrato::create([
            'matricula_id' => $matricula->id,
            'valor_total' => $valor * 12,
            'data_aceite' => '2026-01-10',
        ]);

        ResponsavelFinanceiro::create([
            'contrato_id' => $contrato->id,
            'pessoa_id' => $responsavel->id,
        ]);

        $fatura = Fatura::create([
            'contrato_id' => $contrato->id,
            'vencimento' => $vencimento->toDateString(),
            'status' => $status,
        ]);

        ItemFatura::create([
            'fatura_id' => $fatura->id,
            'descricao' => 'Mensalidade Escolar',
            'quantidade' => 1,
            'valor_unitario' => $valor,
            'desconto' => 0,
        ]);

        return [
            'aluno' => $aluno,
            'responsavel' => $responsavel,
            'contrato' => $contrato,
            'fatura' => $fatura,
        ];
    }

    public function test_processar_regua_diaria_dispara_lembretes_preventivos(): void
    {
        Notification::fake();

        // Criar régua preventiva para 5 dias antes (offset = -5)
        $regra = ReguaCobranca::create([
            'nome' => 'Preventivo 5 Dias',
            'dias_offset' => -5,
            'tipo_gatilho' => 'antes_vencimento',
            'canal' => 'todos',
            'assunto' => 'Sua fatura de {{ALUNO_NOME}} vencerá em 5 dias',
            'mensagem' => 'Prezado {{RESPONSAVEL_NOME}}, a fatura #{{NUMERO_FATURA}} vence em {{DATA_VENCIMENTO}}.',
            'is_ativo' => true,
            'ordem' => 1,
        ]);

        $hoje = Carbon::parse('2026-04-10');
        // Vencimento daqui a 5 dias: 2026-04-15
        $dados = $this->criarEstruturaFinanceira(Carbon::parse('2026-04-15'));
        $fatura = $dados['fatura'];

        $resultado = $this->service->processarReguaDiaria($hoje, false);

        $this->assertEquals(1, $resultado['total_faturas_analisadas']);
        $this->assertEquals(1, $resultado['total_notificacoes_enviadas']);

        // Verifica gravação do log
        $this->assertDatabaseHas('regua_cobranca_logs', [
            'regua_cobranca_id' => $regra->id,
            'fatura_id' => $fatura->id,
            'pessoa_id' => $dados['responsavel']->id,
            'status_envio' => 'sucesso',
        ]);
        $this->assertEquals('2026-04-10', ReguaCobrancaLog::first()->data_envio->format('Y-m-d'));
    }

    public function test_processar_regua_nao_duplica_envios_no_mesmo_dia(): void
    {
        Notification::fake();

        $regra = ReguaCobranca::create([
            'nome' => 'Preventivo 5 Dias',
            'dias_offset' => -5,
            'tipo_gatilho' => 'antes_vencimento',
            'canal' => 'todos',
            'assunto' => 'Aviso',
            'mensagem' => 'Mensagem teste',
            'is_ativo' => true,
        ]);

        $hoje = Carbon::parse('2026-04-10');
        $dados = $this->criarEstruturaFinanceira(Carbon::parse('2026-04-15'));
        $fatura = $dados['fatura'];

        // Primeira execução
        $res1 = $this->service->processarReguaDiaria($hoje, false);
        $this->assertEquals(1, $res1['total_notificacoes_enviadas']);

        // Segunda execução na mesma data
        $res2 = $this->service->processarReguaDiaria($hoje, false);
        $this->assertEquals(0, $res2['total_notificacoes_enviadas']);

        // Deve existir apenas 1 registro no banco
        $totalLogs = ReguaCobrancaLog::where('fatura_id', $fatura->id)->count();
        $this->assertEquals(1, $totalLogs);
    }

    public function test_processar_regua_atualiza_status_para_atrasado_apos_vencimento(): void
    {
        Notification::fake();

        $regra = ReguaCobranca::create([
            'nome' => 'Atraso 3 Dias',
            'dias_offset' => 3,
            'tipo_gatilho' => 'apos_vencimento',
            'canal' => 'todos',
            'assunto' => 'Fatura Atrasada',
            'mensagem' => 'Sua fatura está em atraso',
            'is_ativo' => true,
        ]);

        $hoje = Carbon::parse('2026-04-10');
        // Vencimento há 3 dias: 2026-04-07
        $dados = $this->criarEstruturaFinanceira(Carbon::parse('2026-04-07'), 350.0, 'pendente');
        $fatura = $dados['fatura'];

        $this->assertEquals(StatusFatura::Pendente, $fatura->status);

        $resultado = $this->service->processarReguaDiaria($hoje, false);

        $this->assertEquals(1, $resultado['total_notificacoes_enviadas']);
        // O status deve ter sido atualizado para Atrasado
        $this->assertEquals(StatusFatura::Atrasado, $fatura->fresh()->status);
    }

    public function test_disparar_lembrete_manual_funciona_com_mensagem_personalizada(): void
    {
        Notification::fake();

        $hoje = Carbon::parse('2026-04-10');
        $dados = $this->criarEstruturaFinanceira(Carbon::parse('2026-04-15'));
        $fatura = $dados['fatura'];

        $res = $this->service->dispararLembreteManual(
            $fatura,
            null,
            'Prezado {{RESPONSAVEL_NOME}}, lembrete manual da fatura #{{NUMERO_FATURA}}.',
            'Cobrança Avulsa'
        );

        $this->assertEquals(1, $res['total_enviados']);
    }

    public function test_fatura_totalmente_paga_nao_recebe_lembrete_de_cobranca(): void
    {
        Notification::fake();

        ReguaCobranca::create([
            'nome' => 'Vence Hoje',
            'dias_offset' => 0,
            'tipo_gatilho' => 'no_vencimento',
            'canal' => 'todos',
            'assunto' => 'Vence Hoje',
            'mensagem' => 'Hoje é o vencimento',
            'is_ativo' => true,
        ]);

        $hoje = Carbon::parse('2026-04-10');
        // Fatura com status pago
        $dados = $this->criarEstruturaFinanceira(Carbon::parse('2026-04-10'), 200.0, 'pago');

        $resultado = $this->service->processarReguaDiaria($hoje, false);

        $this->assertEquals(0, $resultado['total_notificacoes_enviadas']);
    }

    public function test_comando_artisan_executa_com_sucesso(): void
    {
        Notification::fake();

        $this->artisan('cobranca:executar-regua', ['--dry-run' => true])
            ->assertExitCode(0);

        $this->artisan('cobranca:executar-regua')
            ->assertExitCode(0);
    }

    public function test_resource_filament_regua_cobranca_carrega_e_funciona(): void
    {
        Permission::findOrCreate('ViewAny:ReguaCobranca', 'web');
        Permission::findOrCreate('Create:ReguaCobranca', 'web');
        Permission::findOrCreate('Execute:ReguaCobranca', 'web');

        $user = User::factory()->create();
        $user->givePermissionTo(['ViewAny:ReguaCobranca', 'Create:ReguaCobranca', 'Execute:ReguaCobranca']);

        $regra = ReguaCobranca::create([
            'nome' => 'Régua de Boas-vindas',
            'dias_offset' => -10,
            'tipo_gatilho' => 'antes_vencimento',
            'canal' => 'todos',
            'assunto' => 'Boas-vindas',
            'mensagem' => 'Aviso de vencimento',
            'is_ativo' => true,
        ]);

        Livewire::actingAs($user)
            ->test(ListReguaCobrancas::class)
            ->assertSee('Régua de Boas-vindas')
            ->assertSee('Executar Régua do Dia')
            ->assertHasNoErrors();
    }
}
