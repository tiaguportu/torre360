<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CanalReguaFollowUp;
use App\Enums\GatilhoReguaFollowUp;
use App\Enums\StatusVisitaInteressado;
use App\Mail\MensagemGenericaMail;
use App\Models\HistoricoContato;
use App\Models\Interessado;
use App\Models\OrigemInteressado;
use App\Models\Pessoa;
use App\Models\ReguaFollowUp;
use App\Models\ReguaFollowUpLog;
use App\Models\StatusInteressado;
use App\Models\TipoContatoInteressado;
use App\Models\Unidade;
use App\Models\User;
use App\Models\VisitaInteressado;
use App\Services\ReguaFollowUpService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\ProibeLazyLoading;
use Tests\TestCase;

/**
 * Régua de follow-up (item 10 da auditoria do CRM): o log só diz "sucesso" se o e-mail saiu, o dia em que o
 * agendador não rodou não perde a mensagem, visita de lead encerrado não dispara nada, `horario_envio` vale, e
 * um lead não recebe uma enxurrada de e-mails no mesmo dia.
 */
class ReguaFollowUpEntregaTest extends TestCase
{
    use ProibeLazyLoading;
    use RefreshDatabase;

    private StatusInteressado $ativo;

    private StatusInteressado $encerrado;

    protected function setUp(): void
    {
        parent::setUp();

        TipoContatoInteressado::firstOrCreate(['nome' => 'E-mail']);
        ReguaFollowUp::query()->delete();
        config([
            'crm.regua.janela_recuperacao_dias' => 2,
            'crm.regua.max_emails_por_lead_dia' => 2,
            'crm.regua.max_tentativas_falha' => 3,
        ]);

        $this->ativo = StatusInteressado::create(['nome' => 'Novo', 'cor' => 'info', 'ordem' => 1, 'is_final' => false, 'is_ganho' => false]);
        $this->encerrado = StatusInteressado::create(['nome' => 'Matriculado', 'cor' => 'success', 'ordem' => 2, 'is_final' => true, 'is_ganho' => true]);
    }

    private function lead(array $lead = [], array $pessoa = []): Interessado
    {
        $pessoaModel = Pessoa::factory()->create($pessoa + ['nome' => 'Maria Silva', 'email' => fake()->unique()->safeEmail(), 'aceita_comunicacao' => true]);

        return Interessado::create($lead + [
            'pessoa_id' => $pessoaModel->id,
            'status_interessado_id' => $this->ativo->id,
            'origem_interessado_id' => OrigemInteressado::firstOrCreate(['nome' => 'Site'])->id,
            'usuario_id' => User::factory()->create()->id,
        ]);
    }

    private function regua(GatilhoReguaFollowUp $gatilho, int $offset = 0, array $extra = []): ReguaFollowUp
    {
        return ReguaFollowUp::create($extra + [
            'nome' => 'Regra '.$gatilho->value.' '.fake()->unique()->word(),
            'gatilho' => $gatilho,
            'dias_offset' => $offset,
            'canal' => CanalReguaFollowUp::Email,
            'assunto' => 'Olá {{PRIMEIRO_NOME}}',
            'mensagem' => '<p>Mensagem</p>',
            'is_ativo' => true,
        ]);
    }

    private function criadoHaDias(Interessado $lead, int $dias): void
    {
        DB::table('interessado')->where('id', $lead->id)->update(['created_at' => Carbon::today()->subDays($dias)->setHour(10)]);
    }

    private function servico(): ReguaFollowUpService
    {
        return new ReguaFollowUpService;
    }

    // ─── Envio e log ────────────────────────────────────────────

    public function test_falha_do_transporte_de_e_mail_vira_falha_no_log_e_nao_entra_no_historico(): void
    {
        Mail::shouldReceive('to')->andReturnSelf();
        Mail::shouldReceive('sendNow')->andThrow(new \RuntimeException('SMTP recusou o destinatário'));

        $lead = $this->lead();
        $this->criadoHaDias($lead, 0);
        $this->regua(GatilhoReguaFollowUp::LeadCriado);

        $resultado = $this->servico()->processarReguaDiaria(Carbon::today());

        $this->assertSame(0, $resultado['total_notificacoes_enviadas']);
        $log = ReguaFollowUpLog::firstOrFail();
        $this->assertSame('falha', $log->status_envio);
        $this->assertStringContainsString('SMTP recusou', $log->erro);
        $this->assertSame(0, HistoricoContato::count(), 'E-mail que não saiu não pode aparecer como contato feito.');
    }

    public function test_falha_e_tentada_uma_vez_por_dia_e_depois_de_tres_dias_desiste(): void
    {
        config(['crm.regua.janela_recuperacao_dias' => 10]);
        Mail::shouldReceive('to')->andReturnSelf();
        Mail::shouldReceive('sendNow')->andThrow(new \RuntimeException('caixa cheia'));

        $this->criadoHaDias($this->lead(), 0);
        $this->regua(GatilhoReguaFollowUp::LeadCriado);
        $servico = $this->servico();

        $servico->processarReguaDiaria(Carbon::today());
        $servico->processarReguaDiaria(Carbon::today()); // mesma data (execução horária seguinte): não repete
        $this->assertSame(1, ReguaFollowUpLog::count());

        $servico->processarReguaDiaria(Carbon::today()->addDay());
        $servico->processarReguaDiaria(Carbon::today()->addDays(2));
        $this->assertSame(3, ReguaFollowUpLog::count());

        $servico->processarReguaDiaria(Carbon::today()->addDays(3)); // 4º dia: já esgotou as 3 tentativas
        $this->assertSame(3, ReguaFollowUpLog::count());
    }

    public function test_email_da_regua_leva_link_de_descadastro_assinado_e_cabecalho_list_unsubscribe(): void
    {
        Mail::fake();
        $lead = $this->lead();
        $this->criadoHaDias($lead, 0);
        $this->regua(GatilhoReguaFollowUp::LeadCriado);

        $this->servico()->processarReguaDiaria(Carbon::today());

        Mail::assertSent(MensagemGenericaMail::class, function (MensagemGenericaMail $mail) use ($lead): bool {
            $link = (string) $mail->linkDescadastro;
            $html = $mail->content()->htmlString;
            $cabecalhos = $mail->headers()->text;

            return str_contains($link, '/comunicacao/descadastrar/'.$lead->pessoa_id)
                && str_contains($link, 'signature=')
                && str_contains($html, 'clique aqui para cancelar')
                && str_contains($html, e($link))
                && $cabecalhos['List-Unsubscribe'] === '<'.$link.'>'
                && $cabecalhos['List-Unsubscribe-Post'] === 'List-Unsubscribe=One-Click';
        });
    }

    public function test_link_da_pesquisa_de_visita_realizada_sai_na_mensagem_mesmo_com_status_em_minusculas(): void
    {
        $lead = $this->lead();
        VisitaInteressado::create([
            'interessado_id' => $lead->id,
            'usuario_id' => $lead->usuario_id,
            'data_hora' => now()->subDays(3),
            'status' => StatusVisitaInteressado::Realizada,
        ]);
        $regra = $this->regua(GatilhoReguaFollowUp::LeadEstagnado, 7, ['mensagem' => 'Conte como foi: {{LINK_PESQUISA}} em {{DATA_VISITA}}']);

        $mensagem = $regra->interpolarMensagem($lead->fresh(['pessoa', 'dependentes.serie', 'usuario']))['mensagem'];

        $this->assertStringNotContainsString('{{LINK_PESQUISA}}', $mensagem);
        $this->assertMatchesRegularExpression('#https?://\S+#', $mensagem, 'O link da pesquisa deveria estar na mensagem.');
        $this->assertStringContainsString(now()->subDays(3)->format('d/m/Y'), $mensagem);
    }

    public function test_nome_da_escola_e_buscado_uma_vez_por_execucao_e_nao_por_mensagem(): void
    {
        Mail::fake();
        Unidade::create(['nome' => 'Colégio Exemplo']);
        foreach (range(1, 4) as $_) {
            $this->criadoHaDias($this->lead(), 0);
        }
        $this->regua(GatilhoReguaFollowUp::LeadCriado, 0, ['mensagem' => 'Bem-vindo ao {{ESCOLA_NOME}}']);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $resultado = $this->servico()->processarReguaDiaria(Carbon::today());
        $consultasDeUnidade = collect(DB::getQueryLog())->filter(fn (array $q): bool => str_contains($q['query'], 'from "unidade"'))->count();
        DB::disableQueryLog();

        $this->assertSame(4, $resultado['total_notificacoes_enviadas']);
        $this->assertSame(1, $consultasDeUnidade);
        $this->assertSame(4, ReguaFollowUpLog::where('mensagem_enviada', 'Bem-vindo ao Colégio Exemplo')->count());
    }

    // ─── Janela de recuperação ──────────────────────────────────

    public function test_lead_criado_ha_ate_dois_dias_ainda_recebe_a_mensagem_do_dia_do_cadastro(): void
    {
        Mail::fake();
        $recente = $this->lead();
        $this->criadoHaDias($recente, 2);
        $antigo = $this->lead();
        $this->criadoHaDias($antigo, 3);
        $this->regua(GatilhoReguaFollowUp::LeadCriado, 0);

        $resultado = $this->servico()->processarReguaDiaria(Carbon::today());

        $this->assertSame(1, $resultado['total_notificacoes_enviadas']);
        $this->assertDatabaseHas('regua_follow_up_logs', ['interessado_id' => $recente->id, 'status_envio' => 'sucesso']);
        $this->assertDatabaseMissing('regua_follow_up_logs', ['interessado_id' => $antigo->id]);

        // Rodar de novo (ou no dia seguinte) não reenvia.
        $this->assertSame(0, $this->servico()->processarReguaDiaria(Carbon::today())['total_notificacoes_enviadas']);
        $this->assertSame(0, $this->servico()->processarReguaDiaria(Carbon::today()->addDay())['total_notificacoes_enviadas']);
    }

    public function test_lembrete_de_visita_so_vale_na_data_exata(): void
    {
        Mail::fake();
        $lead = $this->lead();
        $hoje = VisitaInteressado::create(['interessado_id' => $lead->id, 'data_hora' => Carbon::today()->setHour(15), 'status' => StatusVisitaInteressado::Agendada]);
        $amanha = VisitaInteressado::create(['interessado_id' => $lead->id, 'data_hora' => Carbon::today()->addDay()->setHour(10), 'status' => StatusVisitaInteressado::Agendada]);
        $this->regua(GatilhoReguaFollowUp::VisitaLembrete, 1);

        $this->servico()->processarReguaDiaria(Carbon::today());

        $this->assertDatabaseHas('regua_follow_up_logs', ['visita_interessado_id' => $amanha->id]);
        $this->assertDatabaseMissing('regua_follow_up_logs', ['visita_interessado_id' => $hoje->id]);
    }

    public function test_agradecimento_pos_visita_recupera_visita_de_ate_dois_dias_atras(): void
    {
        Mail::fake();
        $lead = $this->lead();
        $ontem = VisitaInteressado::create(['interessado_id' => $lead->id, 'data_hora' => now()->subDay(), 'status' => StatusVisitaInteressado::Realizada]);
        $outroLead = $this->lead();
        $haQuatroDias = VisitaInteressado::create(['interessado_id' => $outroLead->id, 'data_hora' => now()->subDays(4), 'status' => StatusVisitaInteressado::Realizada]);
        $this->regua(GatilhoReguaFollowUp::VisitaRealizada, 1);

        $this->servico()->processarReguaDiaria(Carbon::today());

        $this->assertDatabaseHas('regua_follow_up_logs', ['visita_interessado_id' => $ontem->id, 'status_envio' => 'sucesso']);
        $this->assertDatabaseMissing('regua_follow_up_logs', ['visita_interessado_id' => $haQuatroDias->id]);
    }

    public function test_gatilhos_de_visita_ignoram_lead_ja_encerrado(): void
    {
        Mail::fake();
        $matriculado = $this->lead(['status_interessado_id' => $this->encerrado->id]);
        VisitaInteressado::create(['interessado_id' => $matriculado->id, 'data_hora' => now()->subDay(), 'status' => StatusVisitaInteressado::Realizada]);
        VisitaInteressado::create(['interessado_id' => $matriculado->id, 'data_hora' => now()->subDay(), 'status' => StatusVisitaInteressado::Faltou]);
        VisitaInteressado::create(['interessado_id' => $matriculado->id, 'data_hora' => now()->addDay(), 'status' => StatusVisitaInteressado::Agendada]);
        $this->regua(GatilhoReguaFollowUp::VisitaRealizada, 1);
        $this->regua(GatilhoReguaFollowUp::VisitaFaltou, 1);
        $this->regua(GatilhoReguaFollowUp::VisitaLembrete, 1);

        $resultado = $this->servico()->processarReguaDiaria(Carbon::today());

        $this->assertSame(0, $resultado['total_candidatos_analisados']);
        Mail::assertNothingSent();
    }

    public function test_contato_atrasado_avisa_uma_vez_por_ciclo_e_de_novo_quando_a_data_e_reagendada(): void
    {
        Mail::fake();
        $lead = $this->lead(['data_proximo_contato' => Carbon::today()->subDay()->setHour(9)]);
        $this->regua(GatilhoReguaFollowUp::ContatoAtrasado, 1);
        $servico = $this->servico();

        $this->assertSame(1, $servico->processarReguaDiaria(Carbon::today())['total_notificacoes_enviadas']);
        $this->assertSame(0, $servico->processarReguaDiaria(Carbon::today()->addDay())['total_notificacoes_enviadas'], 'Mesmo vencimento: já avisado.');

        // Consultor reagenda e a nova data também vence sem contato: é outro ciclo.
        $lead->update(['data_proximo_contato' => Carbon::today()->addDays(4)->setHour(9)]);
        $this->assertSame(1, $servico->processarReguaDiaria(Carbon::today()->addDays(6))['total_notificacoes_enviadas']);
        $this->assertSame(2, ReguaFollowUpLog::where('interessado_id', $lead->id)->where('status_envio', 'sucesso')->count());
    }

    // ─── Horário de disparo ─────────────────────────────────────

    public function test_regra_espera_o_horario_de_disparo_quando_o_chamador_informa_a_hora(): void
    {
        Mail::fake();
        $this->criadoHaDias($this->lead(), 0);
        $this->regua(GatilhoReguaFollowUp::LeadCriado, 0, ['horario_envio' => '14:00:00']);
        $servico = $this->servico();

        $cedo = $servico->processarReguaDiaria(Carbon::today(), false, Carbon::today()->setTime(9, 0));
        $this->assertSame(1, $cedo['total_regras_aguardando_horario']);
        $this->assertSame(0, $cedo['total_notificacoes_enviadas']);

        $naHora = $servico->processarReguaDiaria(Carbon::today(), false, Carbon::today()->setTime(14, 0));
        $this->assertSame(0, $naHora['total_regras_aguardando_horario']);
        $this->assertSame(1, $naHora['total_notificacoes_enviadas']);
    }

    public function test_comando_agendado_respeita_o_horario_e_a_opcao_ignorar_horario_processa_tudo(): void
    {
        Mail::fake();
        $this->travelTo(Carbon::today()->setTime(9, 30));
        $this->criadoHaDias($this->lead(), 0);
        $this->regua(GatilhoReguaFollowUp::LeadCriado, 0, ['horario_envio' => '14:00:00']);

        $this->artisan('crm:executar-regua-follow-up')->assertSuccessful();
        $this->assertSame(0, ReguaFollowUpLog::count(), 'Às 9h30 a regra das 14h ainda não saiu.');

        $this->artisan('crm:executar-regua-follow-up', ['--ignorar-horario' => true])->assertSuccessful();
        $this->assertSame(1, ReguaFollowUpLog::count());
    }

    // ─── Teto diário ────────────────────────────────────────────

    public function test_lead_recebe_no_maximo_dois_emails_da_regua_por_dia_e_o_resto_sai_no_dia_seguinte(): void
    {
        Mail::fake();
        $lead = $this->lead();
        $this->criadoHaDias($lead, 0);
        foreach (range(1, 3) as $_) {
            $this->regua(GatilhoReguaFollowUp::LeadCriado, 0);
        }
        $servico = $this->servico();

        $dia1 = $servico->processarReguaDiaria(Carbon::today());
        $this->assertSame(2, $dia1['total_notificacoes_enviadas']);
        $this->assertSame(1, $dia1['total_adiadas_limite']);
        Mail::assertSentCount(2);

        // Segunda execução do mesmo dia: continua no teto.
        $this->assertSame(0, $servico->processarReguaDiaria(Carbon::today())['total_notificacoes_enviadas']);

        // Dia seguinte (dentro da janela de recuperação): o que ficou para trás sai.
        $dia2 = $servico->processarReguaDiaria(Carbon::today()->addDay());
        $this->assertSame(1, $dia2['total_notificacoes_enviadas']);
        Mail::assertSentCount(3);
    }

    public function test_simulacao_tambem_respeita_o_teto_diario(): void
    {
        Mail::fake();
        $this->criadoHaDias($this->lead(), 0);
        foreach (range(1, 3) as $_) {
            $this->regua(GatilhoReguaFollowUp::LeadCriado, 0);
        }

        $resultado = $this->servico()->processarReguaDiaria(Carbon::today(), true);

        $this->assertSame(2, $resultado['total_notificacoes_enviadas']);
        $this->assertSame(1, $resultado['total_adiadas_limite']);
        $this->assertSame(0, ReguaFollowUpLog::count());
        Mail::assertNothingSent();
    }

    public function test_teto_zero_desliga_o_limite(): void
    {
        Mail::fake();
        config(['crm.regua.max_emails_por_lead_dia' => 0]);
        $this->criadoHaDias($this->lead(), 0);
        foreach (range(1, 3) as $_) {
            $this->regua(GatilhoReguaFollowUp::LeadCriado, 0);
        }

        $this->assertSame(3, $this->servico()->processarReguaDiaria(Carbon::today())['total_notificacoes_enviadas']);
    }

    public function test_teto_diario_nao_conta_avisos_internos_para_a_equipe(): void
    {
        Mail::fake();
        $lead = $this->lead();
        $this->criadoHaDias($lead, 0);
        $this->regua(GatilhoReguaFollowUp::LeadCriado, 0);
        $this->regua(GatilhoReguaFollowUp::LeadCriado, 0);
        foreach (range(1, 2) as $_) {
            $this->regua(GatilhoReguaFollowUp::LeadCriado, 0, ['canal' => CanalReguaFollowUp::NotificacaoSistema]);
        }

        $resultado = $this->servico()->processarReguaDiaria(Carbon::today());

        $this->assertSame(4, $resultado['total_notificacoes_enviadas']);
        $this->assertSame(0, $resultado['total_adiadas_limite']);
    }
}
