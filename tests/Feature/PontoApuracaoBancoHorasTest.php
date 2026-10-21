<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrigemLancamentoBancoHoras;
use App\Enums\StatusTratamentoPonto;
use App\Enums\TipoAcordoBancoHoras;
use App\Enums\TipoTratamentoPonto;
use App\Exceptions\PontoException;
use App\Models\Funcionario;
use App\Models\Pessoa;
use App\Models\PontoAcordoBancoHoras;
use App\Models\PontoBancoHorasLancamento;
use App\Models\PontoConfiguracao;
use App\Models\PontoJornada;
use App\Models\PontoRegistro;
use App\Models\PontoTratamento;
use App\Models\Unidade;
use App\Models\User;
use App\Services\Ponto\BancoHorasService;
use App\Services\Ponto\HoraLegalDesativada;
use App\Services\Ponto\HoraLegalProvider;
use App\Services\Ponto\PontoApuracaoService;
use App\Services\Ponto\PontoRegistroService;
use App\Services\Ponto\PontoTratamentoService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Apuração da jornada, espelho de ponto, banco de horas e tratamento de registros (Onda 41).
 *
 * Calendário de referência: semana de 5 a 9 de outubro de 2026 (segunda a sexta, sem feriado nacional); 12/10 é
 * feriado (Nossa Senhora Aparecida). "Hoje" nos testes é 20/10/2026.
 */
class PontoApuracaoBancoHorasTest extends TestCase
{
    use RefreshDatabase;

    private Unidade $unidade;

    private Funcionario $func;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(HoraLegalProvider::class, new HoraLegalDesativada);
        $this->travelTo(Carbon::parse('2026-10-20 09:00:00'));

        $this->unidade = Unidade::create(['nome' => 'Unidade Central', 'situacao_funcionamento' => '1']);
        PontoConfiguracao::factory()->create(['unidade_id' => $this->unidade->id]);
        $this->func = $this->novoFuncionario();
        PontoJornada::factory()->create(['funcionario_id' => $this->func->id]);
    }

    private function novoFuncionario(): Funcionario
    {
        return Funcionario::factory()->create([
            'pessoa_id' => Pessoa::factory()->create()->id,
            'unidade_id' => $this->unidade->id,
            'data_admissao' => '2025-01-10',
        ]);
    }

    /**
     * Marca o ponto nos horários informados do dia (formato "HH:MM").
     *
     * @param  list<string>  $horas
     */
    private function marcar(string $dia, array $horas, ?Funcionario $func = null): void
    {
        foreach ($horas as $hora) {
            $this->travelTo(Carbon::parse("{$dia} {$hora}:00"));
            app(PontoRegistroService::class)->registrar($func ?? $this->func);
        }

        $this->travelTo(Carbon::parse('2026-10-20 09:00:00'));
    }

    private function apurar(string $dia, ?Funcionario $func = null): array
    {
        return app(PontoApuracaoService::class)->apurarDia($func ?? $this->func, Carbon::parse($dia));
    }

    private function admin(): User
    {
        return User::factory()->create(['activated_at' => now()]);
    }

    private function acordo(array $atributos = []): PontoAcordoBancoHoras
    {
        return PontoAcordoBancoHoras::factory()->create(array_merge(['funcionario_id' => $this->func->id], $atributos));
    }

    // ─── Apuração do dia ─────────────────────────────────────────────

    public function test_dia_cumprido_fecha_com_saldo_zero(): void
    {
        $this->marcar('2026-10-05', ['08:00', '12:00', '13:00', '17:00']);

        $dia = $this->apurar('2026-10-05');

        $this->assertSame('ok', $dia['situacao']);
        $this->assertSame(480, $dia['trabalhado']);
        $this->assertSame(480, $dia['previsto']);
        $this->assertSame(0, $dia['saldo']);
        $this->assertSame(['08:00', '12:00', '13:00', '17:00'], array_column($dia['marcacoes'], 'hora'));
        $this->assertSame([], $dia['alertas']);
    }

    public function test_tolerancia_da_clt_ate_dez_minutos_por_dia_e_depois_conta_tudo(): void
    {
        $this->marcar('2026-10-05', ['08:00', '12:00', '13:00', '17:08']); // 8 min a mais
        $this->marcar('2026-10-06', ['08:00', '12:00', '13:00', '17:11']); // 11 min a mais
        $this->marcar('2026-10-07', ['08:00', '12:00', '13:00', '16:48']); // 12 min a menos
        $this->marcar('2026-10-08', ['08:00', '12:00', '13:00', '16:50']); // 10 min a menos

        $this->assertSame(0, $this->apurar('2026-10-05')['saldo']);
        $this->assertSame(11, $this->apurar('2026-10-06')['saldo']);
        $this->assertSame(-12, $this->apurar('2026-10-07')['saldo']);
        $this->assertSame(0, $this->apurar('2026-10-08')['saldo']);
    }

    public function test_unidade_pode_adotar_tolerancia_menor_mas_nunca_maior_que_dez(): void
    {
        $this->marcar('2026-10-05', ['08:00', '12:00', '13:00', '17:08']);

        PontoConfiguracao::where('unidade_id', $this->unidade->id)->update(['tolerancia_diaria_minutos' => 5]);
        $this->assertSame(8, $this->apurar('2026-10-05')['saldo']);

        PontoConfiguracao::where('unidade_id', $this->unidade->id)->update(['tolerancia_diaria_minutos' => 30]);
        $this->assertSame(0, $this->apurar('2026-10-05')['saldo']);
        $this->marcar('2026-10-06', ['08:00', '12:00', '13:00', '17:20']);
        $this->assertSame(20, $this->apurar('2026-10-06')['saldo'], 'O teto da CLT é de 10 minutos, mesmo que a unidade configure mais.');
    }

    public function test_segundos_sao_desprezados_e_hora_extra_do_dia_soma(): void
    {
        $this->travelTo(Carbon::parse('2026-10-05 08:00:59'));
        app(PontoRegistroService::class)->registrar($this->func);
        $this->travelTo(Carbon::parse('2026-10-05 12:00:01'));
        app(PontoRegistroService::class)->registrar($this->func);
        $this->marcar('2026-10-05', ['13:00', '19:00']);

        $dia = $this->apurar('2026-10-05');

        $this->assertSame(240 + 360, $dia['trabalhado']);
        $this->assertSame(120, $dia['saldo']);
    }

    public function test_dia_incompleto_nao_gera_saldo(): void
    {
        $this->marcar('2026-10-05', ['08:00', '12:00', '13:00']);

        $dia = $this->apurar('2026-10-05');

        $this->assertSame('incompleto', $dia['situacao']);
        $this->assertNull($dia['saldo']);
        $this->assertSame(240, $dia['trabalhado'], 'Conta só os pares completos.');
    }

    public function test_falta_folga_abono_e_dias_sem_fechamento(): void
    {
        $this->assertSame('falta', $this->apurar('2026-10-06')['situacao']);
        $this->assertSame(-480, $this->apurar('2026-10-06')['saldo']);
        $this->assertSame('folga', $this->apurar('2026-10-10')['situacao']); // sábado
        $this->assertSame(0, $this->apurar('2026-10-10')['saldo']);
        $this->assertSame('em_andamento', $this->apurar('2026-10-20')['situacao']);
        $this->assertNull($this->apurar('2026-10-20')['saldo']);
        $this->assertSame('futuro', $this->apurar('2026-10-21')['situacao']);

        app(PontoTratamentoService::class)->registrarAprovado($this->func, TipoTratamentoPonto::Abono, Carbon::parse('2026-10-06'), 'Atestado médico apresentado ao RH', $this->admin(), minutosAbono: 480);
        $this->assertSame('abonado', $this->apurar('2026-10-06')['situacao']);
        $this->assertSame(0, $this->apurar('2026-10-06')['saldo']);
    }

    public function test_feriado_nacional_zera_a_jornada_prevista(): void
    {
        $this->assertSame('folga', $this->apurar('2026-10-12')['situacao']);
        $this->assertSame('Nossa Senhora Aparecida', $this->apurar('2026-10-12')['feriado']);

        $this->marcar('2026-10-12', ['08:00', '12:00']);
        $dia = $this->apurar('2026-10-12');

        $this->assertSame(0, $dia['previsto']);
        $this->assertSame(240, $dia['saldo']);
        $this->assertContains('trabalho_em_feriado', array_column($dia['alertas'], 'codigo'));
    }

    public function test_alertas_de_intervalo_interjornada_excesso_e_falta_de_jornada(): void
    {
        $this->marcar('2026-10-05', ['08:00', '17:00']); // 9 h seguidas
        $this->assertContains('intrajornada', array_column($this->apurar('2026-10-05')['alertas'], 'codigo'));

        $this->marcar('2026-10-06', ['07:00', '12:00', '13:00', '16:00']);
        $this->assertNotContains('intrajornada', array_column($this->apurar('2026-10-06')['alertas'], 'codigo'));

        // Terminou 22:00 (07/10 madrugada seria 08:00): menos de 11 h entre 06/10 e 07/10?  Último de 06/10: 16:00 → ok.
        $this->marcar('2026-10-07', ['08:00', '12:00', '13:00', '23:00']); // 14 h no dia
        $codigos = array_column($this->apurar('2026-10-07')['alertas'], 'codigo');
        $this->assertContains('excesso_jornada', $codigos);

        // 07/10 terminou 23:00; 08/10 começa 07:00 → só 8 h de descanso.
        $this->marcar('2026-10-08', ['07:00', '12:00', '13:00', '16:00']);
        $this->assertContains('interjornada', array_column($this->apurar('2026-10-08')['alertas'], 'codigo'));

        $semJornada = $this->novoFuncionario();
        $this->marcar('2026-10-05', ['08:00', '12:00'], $semJornada);
        $dia = $this->apurar('2026-10-05', $semJornada);
        $this->assertContains('sem_jornada', array_column($dia['alertas'], 'codigo'));
        $this->assertSame(0, $dia['previsto']);
    }

    public function test_jornada_muda_com_a_vigencia(): void
    {
        PontoJornada::query()->update(['vigencia_fim' => '2026-10-07']);
        PontoJornada::factory()->create([
            'funcionario_id' => $this->func->id,
            'vigencia_inicio' => '2026-10-08',
            'minutos_qui' => 360,
            'minutos_sex' => 360,
        ]);

        $this->assertSame(480, $this->apurar('2026-10-07')['previsto']);
        $this->assertSame(360, $this->apurar('2026-10-08')['previsto']);
        $this->assertSame(360, $this->apurar('2026-10-09')['previsto']);
    }

    // ─── Tratamento sobre a apuração ─────────────────────────────────

    public function test_apenas_ajuste_aprovado_conta_na_apuracao_e_o_original_fica_intacto(): void
    {
        $this->marcar('2026-10-05', ['08:00', '12:00', '13:00']);
        $service = app(PontoTratamentoService::class);
        $rh = $this->admin();
        $solicitante = $this->admin();

        $pedido = $service->solicitar($this->func, TipoTratamentoPonto::Inclusao, Carbon::parse('2026-10-05'), 'Esqueci de marcar a saída do dia', $solicitante, momento: Carbon::parse('2026-10-05 17:00:00'));
        $this->assertSame('incompleto', $this->apurar('2026-10-05')['situacao'], 'Pendente não conta.');

        $service->aprovar($pedido, $rh);
        $dia = $this->apurar('2026-10-05');

        $this->assertSame('ok', $dia['situacao']);
        $this->assertSame(480, $dia['trabalhado']);
        $this->assertSame(['08:00', '12:00', '13:00', '17:00'], array_column($dia['marcacoes'], 'hora'));
        $this->assertTrue($dia['marcacoes'][3]['ajustada']);
        $this->assertSame(3, PontoRegistro::count(), 'O original não foi tocado nem acrescentado.');
    }

    public function test_desconsiderar_remove_a_marcacao_da_apuracao_sem_apagar_o_registro(): void
    {
        $this->marcar('2026-10-05', ['08:00', '08:02', '12:00', '13:00', '17:00']);
        $indevida = PontoRegistro::where('funcionario_id', $this->func->id)->orderBy('momento')->get()[1];

        app(PontoTratamentoService::class)->registrarAprovado($this->func, TipoTratamentoPonto::Desconsiderar, Carbon::parse('2026-10-05'), 'Toque duplicado na entrada do dia', $this->admin(), registroId: $indevida->id);

        $dia = $this->apurar('2026-10-05');
        $this->assertSame(['08:00', '12:00', '13:00', '17:00'], array_column($dia['marcacoes'], 'hora'));
        $this->assertSame('ok', $dia['situacao']);
        $this->assertSame(5, PontoRegistro::count());
    }

    public function test_ajuste_recusado_nao_conta(): void
    {
        $this->marcar('2026-10-05', ['08:00', '12:00', '13:00']);
        $service = app(PontoTratamentoService::class);
        $pedido = $service->solicitar($this->func, TipoTratamentoPonto::Inclusao, Carbon::parse('2026-10-05'), 'Esqueci de marcar a saída do dia', $this->admin(), momento: Carbon::parse('2026-10-05 17:00:00'));

        $service->recusar($pedido, $this->admin());

        $this->assertSame('incompleto', $this->apurar('2026-10-05')['situacao']);
        $this->assertSame(StatusTratamentoPonto::Recusado, $pedido->fresh()->status);
    }

    // ─── Espelho ─────────────────────────────────────────────────────

    public function test_espelho_do_mes_totaliza_e_separa_banco_de_hora_extra_a_pagar(): void
    {
        PontoJornada::query()->update(['vigencia_inicio' => '2026-10-05']);
        $this->marcar('2026-10-05', ['08:00', '12:00', '13:00', '19:00']); // +120 sem acordo
        $this->acordo(['vigencia_inicio' => '2026-10-06']);
        $this->marcar('2026-10-06', ['08:00', '12:00', '13:00', '18:00']); // +60 com acordo
        $this->marcar('2026-10-07', ['08:00', '12:00', '13:00', '15:00']); // -120 (débito)
        $this->marcar('2026-10-08', ['08:00', '12:00', '13:00']); // incompleto
        // 09/10: falta

        $espelho = app(PontoApuracaoService::class)->espelho($this->func, 2026, 10, Carbon::parse('2026-10-10'));

        $this->assertCount(31, $espelho['dias']);
        $this->assertSame(120, $espelho['totais']['extras_a_pagar']);
        $this->assertSame(60, $espelho['totais']['extras_banco']);
        $this->assertSame(120 + 480, $espelho['totais']['debitos']); // atraso do dia 7 + falta do dia 9
        $this->assertSame(1, $espelho['totais']['faltas']);
        $this->assertSame(1, $espelho['totais']['incompletos']);
        $this->assertSame('futuro', $espelho['dias'][14]['situacao']);
    }

    // ─── Banco de horas ──────────────────────────────────────────────

    private function banco(): BancoHorasService
    {
        return app(BancoHorasService::class);
    }

    public function test_acordo_valida_prazo_maximo_e_documento(): void
    {
        $this->acordo(['tipo' => TipoAcordoBancoHoras::IndividualEscrito, 'prazo_compensacao_meses' => 6]);
        $this->acordo(['tipo' => TipoAcordoBancoHoras::Coletivo, 'prazo_compensacao_meses' => 12]);
        $mesmoMes = $this->acordo(['tipo' => TipoAcordoBancoHoras::MesmoMes, 'prazo_compensacao_meses' => 3]);
        $this->assertSame(0, $mesmoMes->prazo_compensacao_meses);

        try {
            $this->acordo(['tipo' => TipoAcordoBancoHoras::IndividualEscrito, 'prazo_compensacao_meses' => 7]);
            $this->fail('Acordo individual não passa de 6 meses.');
        } catch (PontoException $e) {
            $this->assertStringContainsString('6 meses', $e->getMessage());
        }

        try {
            $this->acordo(['tipo' => TipoAcordoBancoHoras::Coletivo, 'prazo_compensacao_meses' => 13]);
            $this->fail('Acordo coletivo não passa de 12 meses.');
        } catch (PontoException) {
            // esperado
        }

        $this->expectException(PontoException::class);
        $this->acordo(['documento_referencia' => '']);
    }

    public function test_dia_com_saldo_so_entra_no_banco_com_acordo_vigente_e_nao_duplica(): void
    {
        $this->marcar('2026-10-05', ['08:00', '12:00', '13:00', '19:00']);

        $this->assertNull($this->banco()->lancarApuracao($this->func, Carbon::parse('2026-10-05')), 'Sem acordo: é hora extra a pagar.');
        $this->assertSame(0, PontoBancoHorasLancamento::count());

        $this->acordo();
        $lancamento = $this->banco()->lancarApuracao($this->func, Carbon::parse('2026-10-05'));

        $this->assertSame(120, $lancamento->minutos);
        $this->assertSame('2027-04-05', $lancamento->vence_em->toDateString());
        $this->assertSame(OrigemLancamentoBancoHoras::ApuracaoDia, $lancamento->origem);
        $this->assertNull($this->banco()->lancarApuracao($this->func, Carbon::parse('2026-10-05')), 'Segunda apuração não repete.');
        $this->assertSame(1, PontoBancoHorasLancamento::count());
        $this->assertSame(120, $this->banco()->saldo($this->func));
    }

    public function test_falta_e_dia_incompleto_nao_vao_para_o_banco(): void
    {
        $this->acordo();
        $this->marcar('2026-10-06', ['08:00', '12:00', '13:00']);

        $this->assertNull($this->banco()->lancarApuracao($this->func, Carbon::parse('2026-10-06')));
        $this->assertNull($this->banco()->lancarApuracao($this->func, Carbon::parse('2026-10-07'))); // falta
        $this->assertNull($this->banco()->lancarApuracao($this->func, Carbon::parse('2026-10-20'))); // hoje
        $this->assertSame(0, PontoBancoHorasLancamento::count());
    }

    public function test_ajuste_aprovado_depois_gera_lancamento_corretivo(): void
    {
        $this->acordo();
        $this->marcar('2026-10-05', ['08:00', '12:00', '13:00', '17:00', '18:00', '19:00']);
        $this->assertSame(60, $this->banco()->lancarApuracao($this->func, Carbon::parse('2026-10-05'))->minutos);

        // O RH descobre que o último par foi indevido e o desconsidera.
        $indevida = PontoRegistro::where('funcionario_id', $this->func->id)->orderBy('momento')->get()[5];
        app(PontoTratamentoService::class)->registrarAprovado($this->func, TipoTratamentoPonto::Desconsiderar, Carbon::parse('2026-10-05'), 'Saída marcada por engano no fim do dia', $this->admin(), registroId: $indevida->id);
        // 5 marcações → dia incompleto → nada a corrigir ainda.
        $this->assertSame(60, $this->banco()->saldo($this->func));

        $outra = PontoRegistro::where('funcionario_id', $this->func->id)->orderBy('momento')->get()[4];
        app(PontoTratamentoService::class)->registrarAprovado($this->func, TipoTratamentoPonto::Desconsiderar, Carbon::parse('2026-10-05'), 'Entrada marcada por engano no fim do dia', $this->admin(), registroId: $outra->id);

        $this->assertSame(0, $this->banco()->saldo($this->func), 'O corretivo anula o crédito indevido.');
        $this->assertSame(2, PontoBancoHorasLancamento::count());
        $this->assertSame(-60, PontoBancoHorasLancamento::orderByDesc('id')->first()->minutos);
    }

    public function test_compensacao_exige_acordo_saldo_e_minutos_positivos(): void
    {
        $rh = $this->admin();

        try {
            $this->banco()->compensar($this->func, Carbon::parse('2026-10-15'), 60, $rh);
            $this->fail('Sem acordo.');
        } catch (PontoException $e) {
            $this->assertStringContainsString('acordo', $e->getMessage());
        }

        $this->acordo();

        try {
            $this->banco()->compensar($this->func, Carbon::parse('2026-10-15'), 60, $rh);
            $this->fail('Sem saldo.');
        } catch (PontoException $e) {
            $this->assertStringContainsString('Saldo insuficiente', $e->getMessage());
        }

        $this->marcar('2026-10-05', ['08:00', '12:00', '13:00', '19:00']);
        $this->banco()->lancarApuracao($this->func, Carbon::parse('2026-10-05'));

        $lancamento = $this->banco()->compensar($this->func, Carbon::parse('2026-10-15'), 90, $rh, 'Folga combinada');
        $this->assertSame(-90, $lancamento->minutos);
        $this->assertSame(30, $this->banco()->saldo($this->func));

        $this->expectException(PontoException::class);
        $this->banco()->compensar($this->func, Carbon::parse('2026-10-16'), 0, $rh);
    }

    public function test_debito_abate_primeiro_o_credito_que_vence_antes(): void
    {
        $rh = $this->admin();
        $this->acordo(['vigencia_inicio' => '2026-01-01']);

        $primeiro = $this->banco()->ajustarManualmente($this->func, Carbon::parse('2026-03-02'), 100, 'Saldo trazido do sistema antigo', $rh); // vence 02/09
        $segundo = $this->banco()->ajustarManualmente($this->func, Carbon::parse('2026-05-04'), 100, 'Saldo trazido do sistema antigo 2', $rh); // vence 04/11

        $this->banco()->compensar($this->func, Carbon::parse('2026-06-01'), 130, $rh);
        $posicao = $this->banco()->posicao($this->func, Carbon::parse('2026-06-01'));

        $restantes = collect($posicao['creditos'])->mapWithKeys(fn ($c) => [$c['lancamento']->id => $c['restante']]);
        $this->assertSame([$segundo->id => 70], $restantes->all());
        $this->assertSame(70, $posicao['utilizavel']);
        $this->assertNotNull($primeiro);
    }

    public function test_credito_vencido_vira_horas_a_pagar_uma_unica_vez(): void
    {
        $rh = $this->admin();
        $this->acordo(['vigencia_inicio' => '2026-01-01']);
        $credito = $this->banco()->ajustarManualmente($this->func, Carbon::parse('2026-03-02'), 100, 'Saldo trazido do sistema antigo', $rh); // vence 02/09/2026
        $this->banco()->compensar($this->func, Carbon::parse('2026-04-01'), 40, $rh);

        $this->assertSame(1, $this->banco()->expirarVencidos(Carbon::parse('2026-10-20')));
        $this->assertSame(0, $this->banco()->expirarVencidos(Carbon::parse('2026-10-20')), 'Idempotente.');

        $expiracao = PontoBancoHorasLancamento::where('origem', OrigemLancamentoBancoHoras::Expiracao->value)->first();
        $this->assertSame(-60, $expiracao->minutos, 'Expira só o que restava depois da compensação.');
        $this->assertSame($credito->id, $expiracao->credito_origem_id);
        $this->assertSame('2026-09-03', $expiracao->data->toDateString());
        $this->assertSame(0, $this->banco()->saldo($this->func));
        $this->assertSame(60, $this->banco()->horasAPagar($this->func, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30')));
        $this->assertSame([], $this->banco()->posicao($this->func)['creditos']);
    }

    public function test_credito_dentro_do_prazo_nao_expira_e_mesmo_mes_vence_no_fim_do_mes(): void
    {
        $rh = $this->admin();
        $this->acordo(['vigencia_inicio' => '2026-01-01', 'tipo' => TipoAcordoBancoHoras::MesmoMes, 'prazo_compensacao_meses' => 0]);
        $credito = $this->banco()->ajustarManualmente($this->func, Carbon::parse('2026-09-10'), 60, 'Hora extra do mês de setembro', $rh);

        $this->assertSame('2026-09-30', $credito->vence_em->toDateString());
        $this->assertSame(0, $this->banco()->expirarVencidos(Carbon::parse('2026-09-30')));
        $this->assertSame(1, $this->banco()->expirarVencidos(Carbon::parse('2026-10-01')));
    }

    public function test_saldo_devedor_e_quitado_pelo_proximo_credito(): void
    {
        $rh = $this->admin();
        $this->acordo(['vigencia_inicio' => '2026-01-01']);
        $this->banco()->ajustarManualmente($this->func, Carbon::parse('2026-10-01'), -50, 'Atrasos acumulados a compensar', $rh);

        $posicao = $this->banco()->posicao($this->func);
        $this->assertSame(50, $posicao['devedor']);
        $this->assertSame(0, $posicao['utilizavel']);

        $this->banco()->ajustarManualmente($this->func, Carbon::parse('2026-10-05'), 80, 'Hora extra no evento da escola', $rh);
        $posicao = $this->banco()->posicao($this->func);
        $this->assertSame(0, $posicao['devedor']);
        $this->assertSame(30, $posicao['utilizavel']);
        $this->assertSame(30, $this->banco()->saldo($this->func));
    }

    public function test_ajuste_manual_exige_motivo_e_acordo_para_credito(): void
    {
        $rh = $this->admin();

        try {
            $this->banco()->ajustarManualmente($this->func, Carbon::parse('2026-10-05'), 30, 'Hora extra no evento', $rh);
            $this->fail('Crédito sem acordo.');
        } catch (PontoException $e) {
            $this->assertStringContainsString('acordo', $e->getMessage());
        }

        $this->acordo();

        try {
            $this->banco()->ajustarManualmente($this->func, Carbon::parse('2026-10-05'), 30, 'curto', $rh);
            $this->fail('Motivo curto.');
        } catch (PontoException $e) {
            $this->assertStringContainsString('motivo', $e->getMessage());
        }

        $this->expectException(PontoException::class);
        $this->banco()->ajustarManualmente($this->func, Carbon::parse('2026-10-05'), 0, 'Ajuste sem valor algum', $rh);
    }

    public function test_lancamento_do_banco_e_somente_insercao_no_model_e_no_banco_de_dados(): void
    {
        $this->acordo();
        $lancamento = $this->banco()->ajustarManualmente($this->func, Carbon::parse('2026-10-05'), 30, 'Hora extra no evento da escola', $this->admin());

        try {
            $lancamento->update(['minutos' => 999]);
            $this->fail('Lançamento não pode ser alterado.');
        } catch (PontoException) {
            // esperado
        }

        try {
            $lancamento->delete();
            $this->fail('Lançamento não pode ser apagado.');
        } catch (PontoException) {
            // esperado
        }

        try {
            DB::table('ponto_banco_horas_lancamentos')->where('id', $lancamento->id)->update(['minutos' => 999]);
            $this->fail('O gatilho deveria bloquear.');
        } catch (QueryException) {
            // esperado
        }

        $this->assertSame(30, $this->banco()->saldo($this->func));
    }

    // ─── Tratamento: regras de negócio ───────────────────────────────

    public function test_quem_pediu_o_ajuste_nao_o_aprova_e_ajuste_decidido_nao_se_decide_de_novo(): void
    {
        $service = app(PontoTratamentoService::class);
        $a = $this->admin();
        $b = $this->admin();
        $pedido = $service->solicitar($this->func, TipoTratamentoPonto::Abono, Carbon::parse('2026-10-06'), 'Consulta médica no período da manhã', $a, minutosAbono: 240);

        try {
            $service->aprovar($pedido, $a);
            $this->fail('Autoaprovação.');
        } catch (PontoException $e) {
            $this->assertStringContainsString('não pode aprová-lo', $e->getMessage());
        }

        $aprovado = $service->aprovar($pedido, $b);
        $this->assertSame(StatusTratamentoPonto::Aprovado, $aprovado->status);
        $this->assertSame($b->id, $aprovado->decidido_por_user_id);
        $this->assertNotNull($aprovado->decidido_em);

        $this->expectException(PontoException::class);
        $service->recusar($aprovado, $b);
    }

    public function test_validacoes_do_tratamento(): void
    {
        $service = app(PontoTratamentoService::class);
        $rh = $this->admin();
        $outro = $this->novoFuncionario();
        $this->marcar('2026-10-05', ['08:00'], $outro);
        $registroDeOutro = PontoRegistro::where('funcionario_id', $outro->id)->first();
        $this->marcar('2026-10-06', ['08:00']);
        $meu = PontoRegistro::where('funcionario_id', $this->func->id)->first();

        $casos = [
            'motivo curto' => fn () => $service->solicitar($this->func, TipoTratamentoPonto::Abono, Carbon::parse('2026-10-06'), 'curto', $rh, minutosAbono: 60),
            'dia futuro' => fn () => $service->solicitar($this->func, TipoTratamentoPonto::Abono, Carbon::parse('2026-10-21'), 'Pedido para um dia que não chegou', $rh, minutosAbono: 60),
            'inclusão sem horário' => fn () => $service->solicitar($this->func, TipoTratamentoPonto::Inclusao, Carbon::parse('2026-10-06'), 'Esqueci de marcar a saída do dia', $rh),
            'inclusão em outro dia' => fn () => $service->solicitar($this->func, TipoTratamentoPonto::Inclusao, Carbon::parse('2026-10-06'), 'Esqueci de marcar a saída do dia', $rh, momento: Carbon::parse('2026-10-07 17:00:00')),
            'desconsiderar registro de outro funcionário' => fn () => $service->solicitar($this->func, TipoTratamentoPonto::Desconsiderar, Carbon::parse('2026-10-05'), 'Marcação que não é minha, por engano', $rh, registroId: $registroDeOutro->id),
            'desconsiderar registro de outro dia' => fn () => $service->solicitar($this->func, TipoTratamentoPonto::Desconsiderar, Carbon::parse('2026-10-07'), 'Marcação de outro dia, por engano', $rh, registroId: $meu->id),
            'abono zerado' => fn () => $service->solicitar($this->func, TipoTratamentoPonto::Abono, Carbon::parse('2026-10-06'), 'Abono sem nenhum minuto informado', $rh, minutosAbono: 0),
            'abono acima de 24 h' => fn () => $service->solicitar($this->func, TipoTratamentoPonto::Abono, Carbon::parse('2026-10-06'), 'Abono com minutos demais no dia', $rh, minutosAbono: 1441),
        ];

        foreach ($casos as $nome => $caso) {
            try {
                $caso();
                $this->fail("Deveria recusar: {$nome}.");
            } catch (PontoException) {
                // esperado
            }
        }

        $service->solicitar($this->func, TipoTratamentoPonto::Desconsiderar, Carbon::parse('2026-10-06'), 'Marcação duplicada na entrada do dia', $rh, registroId: $meu->id);

        $this->expectException(PontoException::class);
        $this->expectExceptionMessage('já tem um pedido');
        $service->solicitar($this->func, TipoTratamentoPonto::Desconsiderar, Carbon::parse('2026-10-06'), 'Segundo pedido para a mesma marcação', $rh, registroId: $meu->id);
    }

    public function test_motivo_do_ajuste_fica_criptografado_e_fora_do_log(): void
    {
        $tratamento = app(PontoTratamentoService::class)->solicitar($this->func, TipoTratamentoPonto::Abono, Carbon::parse('2026-10-06'), 'Atestado de cirurgia do joelho CID M23', $this->admin(), minutosAbono: 480);

        $bruto = DB::table('ponto_tratamentos')->where('id', $tratamento->id)->value('motivo');
        $this->assertStringNotContainsString('cirurgia', (string) $bruto);
        $this->assertSame('Atestado de cirurgia do joelho CID M23', $tratamento->fresh()->motivo);

        $tratamento->update(['status' => StatusTratamentoPonto::Aprovado]);
        $logs = Activity::where('log_name', 'ponto')->get()->toJson();
        $this->assertStringNotContainsString('cirurgia', $logs);
        $this->assertStringNotContainsString('CID', $logs);
    }

    public function test_tratamento_nao_e_editado_nem_apagado_pela_policy(): void
    {
        $this->actingAs($this->admin());
        $tratamento = PontoTratamento::create([
            'unidade_id' => $this->unidade->id,
            'funcionario_id' => $this->func->id,
            'data' => '2026-10-06',
            'tipo' => TipoTratamentoPonto::Abono,
            'minutos_abono' => 60,
            'motivo' => 'Consulta médica de rotina',
            'status' => StatusTratamentoPonto::Pendente,
        ]);

        $this->assertFalse(auth()->user()->can('update', $tratamento));
        $this->assertFalse(auth()->user()->can('delete', $tratamento));
    }

    // ─── Comandos agendados ──────────────────────────────────────────

    public function test_comando_apura_o_dia_anterior_e_lanca_no_banco(): void
    {
        $this->acordo();
        $this->marcar('2026-10-19', ['08:00', '12:00', '13:00', '18:00']);
        $this->func->unidade_id = $this->unidade->id;

        $this->artisan('ponto:apurar-dia')->assertSuccessful();

        $this->assertSame(60, $this->banco()->saldo($this->func));
        $this->artisan('ponto:apurar-dia')->assertSuccessful();
        $this->assertSame(1, PontoBancoHorasLancamento::count(), 'Rodar de novo não duplica.');

        $this->artisan('ponto:apurar-dia', ['--data' => '2026-10-20'])->assertFailed();
    }

    public function test_comando_expira_creditos_vencidos(): void
    {
        $this->acordo(['vigencia_inicio' => '2026-01-01']);
        $this->banco()->ajustarManualmente($this->func, Carbon::parse('2026-03-02'), 100, 'Saldo trazido do sistema antigo', $this->admin());

        $this->artisan('ponto:expirar-banco-horas')->assertSuccessful();

        $this->assertSame(0, $this->banco()->saldo($this->func));
    }
}
