<?php

namespace Tests\Feature;

use App\Enums\CanalReguaFollowUp;
use App\Enums\GatilhoReguaFollowUp;
use App\Enums\StatusVisitaInteressado;
use App\Filament\Resources\ReguaFollowUps\Pages\ListReguaFollowUps;
use App\Mail\MensagemGenericaMail;
use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\ReguaFollowUp;
use App\Models\ReguaFollowUpLog;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Models\User;
use App\Models\VisitaInteressado;
use App\Services\ReguaFollowUpService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

class ReguaFollowUpTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        TipoContatoInteressado::firstOrCreate(['id' => 3], ['nome' => 'E-mail']);
        ReguaFollowUp::query()->delete();
    }

    private function criarLeadCompleto(array $atributosPessoa = [], array $atributosLead = []): Interessado
    {
        $status = StatusInteressado::firstOrCreate(
            ['nome' => 'Novo'],
            ['cor' => 'info', 'ordem' => 1, 'is_final' => false, 'is_ganho' => false]
        );

        $origem = OrigemInteressado::firstOrCreate(['nome' => 'Site']);
        $consultor = User::factory()->create();

        $pessoa = Pessoa::factory()->create(array_merge([
            'nome' => 'Maria Silva',
            'email' => 'maria@teste.com',
            'aceita_comunicacao' => true,
        ], $atributosPessoa));

        return Interessado::create(array_merge([
            'pessoa_id' => $pessoa->id,
            'status_interessado_id' => $status->id,
            'origem_interessado_id' => $origem->id,
            'usuario_id' => $consultor->id,
            'temperatura' => 'morno',
        ], $atributosLead));
    }

    public function test_regua_visita_lembrete_envia_email_e_registra_historico(): void
    {
        Mail::fake();

        $lead = $this->criarLeadCompleto();

        $dataAmanha = Carbon::today()->addDay()->setHour(14)->setMinute(30);
        $visita = VisitaInteressado::create([
            'interessado_id' => $lead->id,
            'usuario_id' => $lead->usuario_id,
            'data_hora' => $dataAmanha,
            'status' => StatusVisitaInteressado::Agendada,
        ]);

        $regra = ReguaFollowUp::create([
            'nome' => 'Lembrete D-1 Visita',
            'gatilho' => GatilhoReguaFollowUp::VisitaLembrete,
            'dias_offset' => 1,
            'canal' => CanalReguaFollowUp::Email,
            'assunto' => 'Lembrete: Visita amanhã para {{NOME_RESPONSAVEL}}',
            'mensagem' => 'Olá {{NOME_RESPONSAVEL}}, sua visita é dia {{DATA_VISITA}} às {{HORARIO_VISITA}} com {{NOME_CONSULTOR}}.',
            'is_ativo' => true,
            'horario_envio' => '08:00:00',
        ]);

        $service = app(ReguaFollowUpService::class);
        $res = $service->processarReguaDiaria(Carbon::today());

        $this->assertEquals(1, $res['total_notificacoes_enviadas']);

        Mail::assertQueued(MensagemGenericaMail::class, function (MensagemGenericaMail $mail) use ($lead) {
            return $mail->hasTo($lead->pessoa->email)
                && str_contains($mail->assunto, 'Maria Silva')
                && str_contains($mail->corpoHtml, '14:30');
        });

        $this->assertDatabaseHas('regua_follow_up_logs', [
            'regua_follow_up_id' => $regra->id,
            'interessado_id' => $lead->id,
            'visita_interessado_id' => $visita->id,
            'status_envio' => 'sucesso',
        ]);

        $this->assertDatabaseHas('historico_contato', [
            'interessado_id' => $lead->id,
            'resultado' => 'contato_realizado',
        ]);
    }

    public function test_regua_follow_up_idempotencia_nao_envia_duas_vezes(): void
    {
        Mail::fake();

        $lead = $this->criarLeadCompleto();
        $dataAmanha = Carbon::today()->addDay()->setHour(10)->setMinute(0);
        VisitaInteressado::create([
            'interessado_id' => $lead->id,
            'usuario_id' => $lead->usuario_id,
            'data_hora' => $dataAmanha,
            'status' => StatusVisitaInteressado::Agendada,
        ]);

        ReguaFollowUp::create([
            'nome' => 'Lembrete D-1',
            'gatilho' => GatilhoReguaFollowUp::VisitaLembrete,
            'dias_offset' => 1,
            'canal' => CanalReguaFollowUp::Email,
            'assunto' => 'Lembrete de Visita',
            'mensagem' => 'Mensagem de lembrete',
            'is_ativo' => true,
        ]);

        $service = app(ReguaFollowUpService::class);

        // Primeira execução
        $res1 = $service->processarReguaDiaria(Carbon::today());
        $this->assertEquals(1, $res1['total_notificacoes_enviadas']);
        Mail::assertQueuedCount(1);

        // Segunda execução na mesma data
        $res2 = $service->processarReguaDiaria(Carbon::today());
        $this->assertEquals(0, $res2['total_notificacoes_enviadas']);
        Mail::assertQueuedCount(1); // Continua sendo 1
    }

    public function test_regua_respeita_opt_out_lgpd(): void
    {
        Mail::fake();

        // Lead cujo responsável não aceita comunicações
        $lead = $this->criarLeadCompleto(['aceita_comunicacao' => false]);
        $dataAmanha = Carbon::today()->addDay();
        VisitaInteressado::create([
            'interessado_id' => $lead->id,
            'usuario_id' => $lead->usuario_id,
            'data_hora' => $dataAmanha,
            'status' => StatusVisitaInteressado::Agendada,
        ]);

        ReguaFollowUp::create([
            'nome' => 'Lembrete LGPD',
            'gatilho' => GatilhoReguaFollowUp::VisitaLembrete,
            'dias_offset' => 1,
            'canal' => CanalReguaFollowUp::Email,
            'assunto' => 'Lembrete',
            'mensagem' => 'Mensagem',
            'is_ativo' => true,
        ]);

        $service = app(ReguaFollowUpService::class);
        $res = $service->processarReguaDiaria(Carbon::today());

        $this->assertEquals(0, $res['total_notificacoes_enviadas']);
        Mail::assertNothingSent();

        $this->assertDatabaseHas('regua_follow_up_logs', [
            'interessado_id' => $lead->id,
            'status_envio' => 'falha',
        ]);
    }

    public function test_regua_visita_realizada_envia_agradecimento(): void
    {
        Mail::fake();

        $lead = $this->criarLeadCompleto();
        $dataOntem = Carbon::today()->subDay()->setHour(15);
        $visita = VisitaInteressado::create([
            'interessado_id' => $lead->id,
            'usuario_id' => $lead->usuario_id,
            'data_hora' => $dataOntem,
            'status' => StatusVisitaInteressado::Realizada,
        ]);

        $regra = ReguaFollowUp::create([
            'nome' => 'Pós-Visita Realizada',
            'gatilho' => GatilhoReguaFollowUp::VisitaRealizada,
            'dias_offset' => 1,
            'canal' => CanalReguaFollowUp::Email,
            'assunto' => 'Obrigado por nos visitar!',
            'mensagem' => 'Olá {{NOME_RESPONSAVEL}}, foi ótimo receber você!',
            'is_ativo' => true,
        ]);

        $service = app(ReguaFollowUpService::class);
        $res = $service->processarReguaDiaria(Carbon::today());

        $this->assertEquals(1, $res['total_notificacoes_enviadas']);
        Mail::assertQueued(MensagemGenericaMail::class);

        $this->assertDatabaseHas('regua_follow_up_logs', [
            'regua_follow_up_id' => $regra->id,
            'visita_interessado_id' => $visita->id,
            'status_envio' => 'sucesso',
        ]);
    }

    public function test_regua_lead_estagnado_gera_notificacao_sistema_para_consultor(): void
    {
        $consultor = User::factory()->create();
        $lead = $this->criarLeadCompleto([], [
            'usuario_id' => $consultor->id,
        ]);

        DB::table('interessado')->where('id', $lead->id)->update([
            'created_at' => Carbon::today()->subDays(10),
            'updated_at' => Carbon::today()->subDays(10),
        ]);

        $regra = ReguaFollowUp::create([
            'nome' => 'Alerta Estagnado',
            'gatilho' => GatilhoReguaFollowUp::LeadEstagnado,
            'dias_offset' => 7,
            'canal' => CanalReguaFollowUp::NotificacaoSistema,
            'assunto' => 'Lead sem contato há 7 dias: {{NOME_RESPONSAVEL}}',
            'mensagem' => 'Acesse o lead para retomar o contato.',
            'is_ativo' => true,
        ]);

        $service = app(ReguaFollowUpService::class);
        $res = $service->processarReguaDiaria(Carbon::today());

        $this->assertEquals(1, $res['total_notificacoes_enviadas']);

        $this->assertDatabaseHas('regua_follow_up_logs', [
            'regua_follow_up_id' => $regra->id,
            'interessado_id' => $lead->id,
            'canal' => CanalReguaFollowUp::NotificacaoSistema->value,
            'status_envio' => 'sucesso',
        ]);
    }

    public function test_comando_artisan_executa_em_modo_dry_run_sem_disparar_mensagens(): void
    {
        Mail::fake();

        $lead = $this->criarLeadCompleto();
        VisitaInteressado::create([
            'interessado_id' => $lead->id,
            'usuario_id' => $lead->usuario_id,
            'data_hora' => Carbon::today()->addDay(),
            'status' => StatusVisitaInteressado::Agendada,
        ]);

        ReguaFollowUp::create([
            'nome' => 'Lembrete Dry Run',
            'gatilho' => GatilhoReguaFollowUp::VisitaLembrete,
            'dias_offset' => 1,
            'canal' => CanalReguaFollowUp::Email,
            'assunto' => 'Lembrete',
            'mensagem' => 'Mensagem',
            'is_ativo' => true,
        ]);

        $this->artisan('crm:executar-regua-follow-up', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('MODO DE SIMULAÇÃO (DRY-RUN) ATIVO')
            ->expectsOutputToContain('Sim');

        Mail::assertNothingSent();
        $this->assertEquals(0, ReguaFollowUpLog::count());
    }

    public function test_listagem_renderiza_e_possui_ajuda_contextual(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['activated_at' => now()]);
        $admin->assignRole('super_admin');

        $this->actingAs($admin)
            ->get('/admin/regua-follow-ups')
            ->assertOk();

        Livewire::actingAs($admin)
            ->test(ListReguaFollowUps::class)
            ->mountAction('ajuda')
            ->assertHasNoErrors();
    }
}
