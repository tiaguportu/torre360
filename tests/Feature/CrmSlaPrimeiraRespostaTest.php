<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\StatusInteressado;
use App\Models\User;
use App\Services\LeadSlaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CrmSlaPrimeiraRespostaTest extends TestCase
{
    use RefreshDatabase;

    private StatusInteressado $statusNovo;

    private StatusInteressado $statusMatriculado;

    private OrigemInteressado $origem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->statusNovo = StatusInteressado::create([
            'nome' => 'Novo',
            'cor' => 'info',
            'ordem' => 1,
            'is_final' => false,
            'is_ganho' => false,
        ]);

        $this->statusMatriculado = StatusInteressado::create([
            'nome' => 'Matriculado',
            'cor' => 'success',
            'ordem' => 5,
            'is_final' => true,
            'is_ganho' => true,
        ]);

        $this->origem = OrigemInteressado::create(['nome' => 'Site']);

        // Configuração de horário comercial: Seg-Sex das 08:00 às 18:00
        config([
            'crm.sla.primeira_resposta_minutos_padrao' => 120,
            'crm.sla.horario_comercial.inicio' => '08:00',
            'crm.sla.horario_comercial.fim' => '18:00',
            'crm.sla.horario_comercial.dias_uteis' => [1, 2, 3, 4, 5],
        ]);
    }

    private function criarLead(array $dados = []): Interessado
    {
        if (! isset($dados['pessoa_id'])) {
            $dados['pessoa_id'] = Pessoa::create([
                'nome' => 'Lead '.fake()->name(),
                'email' => fake()->unique()->safeEmail(),
                'telefone' => '119'.fake()->numerify('########'),
            ])->id;
        }

        return Interessado::create(array_merge([
            'status_interessado_id' => $this->statusNovo->id,
            'origem_interessado_id' => $this->origem->id,
        ], $dados));
    }

    public function test_calculo_data_limite_dentro_do_horario_comercial(): void
    {
        // Terça-feira, 14 de Outubro às 10:00
        $dataCriacao = Carbon::create(2026, 10, 13, 10, 0, 0);

        $lead = $this->criarLead();
        $lead->forceFill(['created_at' => $dataCriacao])->saveQuietly();

        $slaService = app(LeadSlaService::class);
        $limite = $slaService->calcularDataLimite($lead, $dataCriacao);

        // 120 minutos (2 horas) a partir das 10:00 = 12:00 do mesmo dia
        $this->assertSame('2026-10-13 12:00:00', $limite->format('Y-m-d H:i:s'));
    }

    public function test_calculo_data_limite_atravessa_final_do_expediente(): void
    {
        // Terça-feira às 17:00 (expediente encerra às 18:00). SLA de 120 minutos.
        $dataCriacao = Carbon::create(2026, 10, 13, 17, 0, 0);

        $lead = $this->criarLead();
        $lead->forceFill(['created_at' => $dataCriacao])->saveQuietly();

        $slaService = app(LeadSlaService::class);
        $limite = $slaService->calcularDataLimite($lead, $dataCriacao);

        // Consome 60 minutos na terça até 18:00. Os 60 minutos restantes contam na quarta das 08:00 às 09:00.
        $this->assertSame('2026-10-14 09:00:00', $limite->format('Y-m-d H:i:s'));
    }

    public function test_calculo_data_limite_criado_fora_do_expediente_noturno(): void
    {
        // Quarta-feira às 21:30 da noite. SLA de 120 minutos.
        $dataCriacao = Carbon::create(2026, 10, 14, 21, 30, 0);

        $lead = $this->criarLead();
        $lead->forceFill(['created_at' => $dataCriacao])->saveQuietly();

        $slaService = app(LeadSlaService::class);
        $limite = $slaService->calcularDataLimite($lead, $dataCriacao);

        // O relógio só inicia na quinta-feira às 08:00 + 120 min = 10:00
        $this->assertSame('2026-10-15 10:00:00', $limite->format('Y-m-d H:i:s'));
    }

    public function test_calculo_data_limite_criado_no_final_de_semana(): void
    {
        // Sábado às 14:00 (17 de Outubro). SLA de 120 minutos.
        $dataCriacao = Carbon::create(2026, 10, 17, 14, 0, 0);

        $lead = $this->criarLead();
        $lead->forceFill(['created_at' => $dataCriacao])->saveQuietly();

        $slaService = app(LeadSlaService::class);
        $limite = $slaService->calcularDataLimite($lead, $dataCriacao);

        // O relógio só inicia na segunda-feira (19 de Outubro) às 08:00 + 120 min = 10:00
        $this->assertSame('2026-10-19 10:00:00', $limite->format('Y-m-d H:i:s'));
    }

    public function test_calculo_data_limite_atravessa_final_de_semana(): void
    {
        // Sexta-feira às 17:30 (16 de Outubro). SLA de 60 minutos.
        $dataCriacao = Carbon::create(2026, 10, 16, 17, 30, 0);

        $lead = $this->criarLead(['sla_primeira_resposta_minutos' => 60]);
        $lead->forceFill(['created_at' => $dataCriacao])->saveQuietly();

        $slaService = app(LeadSlaService::class);
        $limite = $slaService->calcularDataLimite($lead, $dataCriacao);

        // Gasta 30 min na sexta até 18:00. Os 30 min restantes contam na segunda às 08:00 até 08:30.
        $this->assertSame('2026-10-19 08:30:00', $limite->format('Y-m-d H:i:s'));
    }

    public function test_prazo_customizado_por_lead_sobrepoe_padrao_do_sistema(): void
    {
        $dataCriacao = Carbon::create(2026, 10, 13, 10, 0, 0);

        $leadPadrao = $this->criarLead(['sla_primeira_resposta_minutos' => null]);
        $leadCustom = $this->criarLead(['sla_primeira_resposta_minutos' => 30]);

        $slaService = app(LeadSlaService::class);

        $this->assertSame(120, $slaService->prazoMinutosParaLead($leadPadrao));
        $this->assertSame(30, $slaService->prazoMinutosParaLead($leadCustom));

        $limiteCustom = $slaService->calcularDataLimite($leadCustom, $dataCriacao);
        $this->assertSame('2026-10-13 10:30:00', $limiteCustom->format('Y-m-d H:i:s'));
    }

    public function test_sla_estourado_retorna_false_se_houve_primeira_resposta(): void
    {
        $dataCriacao = Carbon::create(2026, 10, 13, 9, 0, 0);

        $lead = $this->criarLead([
            'data_primeiro_contato' => Carbon::create(2026, 10, 13, 10, 15, 0),
        ]);
        $lead->forceFill(['created_at' => $dataCriacao])->saveQuietly();

        $agora = Carbon::create(2026, 10, 13, 15, 0, 0); // Limite era 11:00

        $slaService = app(LeadSlaService::class);

        // Mesmo com agora > limite, SLA foi cumprido pois data_primeiro_contato existe
        $this->assertFalse($slaService->slaEstourado($lead, $agora));
    }

    public function test_sla_estourado_retorna_true_quando_passa_do_limite_sem_resposta(): void
    {
        $dataCriacao = Carbon::create(2026, 10, 13, 9, 0, 0);

        $lead = $this->criarLead(['data_primeiro_contato' => null]);
        $lead->forceFill(['created_at' => $dataCriacao])->saveQuietly();

        $antesDoLimite = Carbon::create(2026, 10, 13, 10, 30, 0); // limite é 11:00
        $depoisDoLimite = Carbon::create(2026, 10, 13, 11, 30, 0);

        $slaService = app(LeadSlaService::class);

        $this->assertFalse($slaService->slaEstourado($lead, $antesDoLimite));
        $this->assertTrue($slaService->slaEstourado($lead, $depoisDoLimite));
    }

    public function test_verificar_e_notificar_estouros_dispara_alerta_ao_consultor_uma_unica_vez(): void
    {
        $consultor = User::factory()->create();

        $dataCriacao = Carbon::create(2026, 10, 13, 8, 0, 0);

        $lead = $this->criarLead([
            'usuario_id' => $consultor->id,
            'data_primeiro_contato' => null,
            'sla_estouro_notificado_em' => null,
            'sla_primeira_resposta_minutos' => 60,
        ]);
        $lead->forceFill(['created_at' => $dataCriacao])->saveQuietly();

        // Simula execução às 10:00 (limite era 09:00, estourado)
        Carbon::setTestNow(Carbon::create(2026, 10, 13, 10, 0, 0));

        $slaService = app(LeadSlaService::class);

        $notificados = $slaService->verificarENotificarEstouros();
        $this->assertSame(1, $notificados);

        // Verifica que o lead foi marcado com data de notificação
        $lead->refresh();
        $this->assertNotNull($lead->sla_estouro_notificado_em);

        // Verifica que notificação foi registrada no banco de dados para o consultor
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $consultor->id,
            'notifiable_type' => User::class,
        ]);

        // Execução subsequente: não notifica novamente (idempotência)
        $notificadosNovamente = $slaService->verificarENotificarEstouros();
        $this->assertSame(0, $notificadosNovamente);

        Carbon::setTestNow();
    }

    public function test_comando_artisan_executa_e_notifica_estouros(): void
    {
        $consultor = User::factory()->create();

        $dataCriacao = Carbon::create(2026, 10, 13, 8, 0, 0);

        $lead = $this->criarLead([
            'usuario_id' => $consultor->id,
            'data_primeiro_contato' => null,
            'sla_estouro_notificado_em' => null,
            'sla_primeira_resposta_minutos' => 60,
        ]);
        $lead->forceFill(['created_at' => $dataCriacao])->saveQuietly();

        Carbon::setTestNow(Carbon::create(2026, 10, 13, 10, 0, 0));

        $exitCode = Artisan::call('crm:verificar-sla-estourado');
        $this->assertSame(0, $exitCode);

        $lead->refresh();
        $this->assertNotNull($lead->sla_estouro_notificado_em);

        Carbon::setTestNow();
    }
}
