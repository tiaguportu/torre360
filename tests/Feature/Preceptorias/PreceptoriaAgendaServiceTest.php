<?php

namespace Tests\Feature\Preceptorias;

use App\Models\DiaNaoLetivo;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Preceptoria;
use App\Notifications\Preceptorias\LembretePreceptoriaNotification;
use App\Services\PreceptoriaAgendaService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PreceptoriaAgendaServiceTest extends TestCase
{
    use RefreshDatabase;

    private PreceptoriaAgendaService $servico;

    protected function setUp(): void
    {
        parent::setUp();

        $this->servico = new PreceptoriaAgendaService;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function regras(string $inicio, string $fim, array $extra = []): array
    {
        return array_merge([
            'inicio' => $inicio,
            'fim' => $fim,
            'ignorar_fins_de_semana' => false,
            'ignorar_feriados' => false,
            'ignorar_dias_nao_letivos' => false,
        ], $extra);
    }

    public function test_sem_nenhum_ignorar_gera_todos_os_dias_da_janela(): void
    {
        // 01/09/2026 (terça) a 07/09/2026 (segunda): 7 dias corridos
        $resultado = $this->servico->calcularDatas($this->regras('2026-09-01', '2026-09-07'));

        $this->assertCount(7, $resultado['datas']);
        $this->assertSame([], $resultado['ignoradas']);
    }

    public function test_ignora_fins_de_semana_e_feriado_nacional(): void
    {
        // 05 e 06/09 são sábado e domingo; 07/09 é a Independência
        $resultado = $this->servico->calcularDatas($this->regras('2026-09-01', '2026-09-07', [
            'ignorar_fins_de_semana' => true,
            'ignorar_feriados' => true,
        ]));

        $this->assertSame(['2026-09-01', '2026-09-02', '2026-09-03', '2026-09-04'], $resultado['datas']);

        $tipos = collect($resultado['ignoradas'])->pluck('tipo', 'data')->all();
        $this->assertSame([
            '2026-09-05' => PreceptoriaAgendaService::TIPO_FIM_DE_SEMANA,
            '2026-09-06' => PreceptoriaAgendaService::TIPO_FIM_DE_SEMANA,
            '2026-09-07' => PreceptoriaAgendaService::TIPO_FERIADO,
        ], $tipos);
    }

    public function test_feriado_so_e_ignorado_quando_pedido(): void
    {
        // 21/04/2026 (terça) é Tiradentes
        $regras = $this->regras('2026-04-13', '2026-04-24', ['dias_semana' => [2, 4]]);

        $this->assertSame(
            ['2026-04-14', '2026-04-16', '2026-04-21', '2026-04-23'],
            $this->servico->calcularDatas($regras)['datas'],
        );

        $this->assertSame(
            ['2026-04-14', '2026-04-16', '2026-04-23'],
            $this->servico->calcularDatas([...$regras, 'ignorar_feriados' => true])['datas'],
        );
    }

    public function test_sexta_feira_santa_conta_como_feriado(): void
    {
        // Páscoa 2026 é 05/04, então a Sexta-Feira Santa é 03/04
        $resultado = $this->servico->calcularDatas($this->regras('2026-04-01', '2026-04-06', ['ignorar_feriados' => true]));

        $this->assertNotContains('2026-04-03', $resultado['datas']);
        $this->assertSame('Feriado: Sexta-Feira Santa', collect($resultado['ignoradas'])->firstWhere('data', '2026-04-03')['motivo']);
    }

    public function test_ignora_dia_nao_letivo_do_periodo_do_ciclo(): void
    {
        $periodo = PeriodoLetivo::factory()->create();
        $outroPeriodo = PeriodoLetivo::factory()->create();

        DiaNaoLetivo::create(['periodo_letivo_id' => $periodo->id, 'data' => '2026-09-02', 'descricao' => 'Recesso', 'flag_ativo' => true]);
        DiaNaoLetivo::create(['periodo_letivo_id' => $periodo->id, 'data' => '2026-09-03', 'descricao' => 'Desativado', 'flag_ativo' => false]);

        $regras = $this->regras('2026-09-01', '2026-09-04', ['ignorar_dias_nao_letivos' => true, 'periodo_letivo_id' => $periodo->id]);
        $resultado = $this->servico->calcularDatas($regras);

        $this->assertSame(['2026-09-01', '2026-09-03', '2026-09-04'], $resultado['datas']);
        $this->assertSame('Dia não letivo: Recesso', $resultado['ignoradas'][0]['motivo']);

        // outro período letivo: o recesso não vale
        $this->assertContains('2026-09-02', $this->servico->calcularDatas([...$regras, 'periodo_letivo_id' => $outroPeriodo->id])['datas']);

        // sem período (ciclo sem período letivo): vale o de qualquer período
        $this->assertNotContains('2026-09-02', $this->servico->calcularDatas([...$regras, 'periodo_letivo_id' => null])['datas']);

        // desligado: entra
        $this->assertContains('2026-09-02', $this->servico->calcularDatas([...$regras, 'ignorar_dias_nao_letivos' => false])['datas']);
    }

    public function test_repete_a_cada_n_semanas_contando_da_semana_inicial(): void
    {
        $resultado = $this->servico->calcularDatas($this->regras('2026-09-01', '2026-09-30', [
            'dias_semana' => [2], // terças
            'intervalo_semanas' => 2,
        ]));

        $this->assertSame(['2026-09-01', '2026-09-15', '2026-09-29'], $resultado['datas']);
    }

    public function test_janela_invertida_nao_gera_datas(): void
    {
        $this->assertSame(
            ['datas' => [], 'ignoradas' => []],
            $this->servico->calcularDatas($this->regras('2026-09-10', '2026-09-01')),
        );
    }

    public function test_conflito_considera_sobreposicao_mas_nao_horarios_que_so_se_encostam(): void
    {
        $professor = Pessoa::factory()->create();
        Preceptoria::factory()->create(['professor_id' => $professor->id, 'data' => '2026-09-03', 'hora_inicio' => '14:00:00', 'hora_fim' => '14:30:00']);

        $this->assertArrayHasKey('2026-09-03', $this->servico->conflitosDoProfessor($professor->id, ['2026-09-03'], '14:15', '14:45'));
        $this->assertArrayHasKey('2026-09-03', $this->servico->conflitosDoProfessor($professor->id, ['2026-09-03'], '13:45', '14:15'));
        $this->assertSame([], $this->servico->conflitosDoProfessor($professor->id, ['2026-09-03'], '14:30', '15:00'));
        $this->assertSame([], $this->servico->conflitosDoProfessor($professor->id, ['2026-09-03'], '13:30', '14:00'));
        $this->assertSame([], $this->servico->conflitosDoProfessor($professor->id, ['2026-09-04'], '14:00', '14:30'));

        // outro professor no mesmo horário não conflita
        $this->assertSame([], $this->servico->conflitosDoProfessor(Pessoa::factory()->create()->id, ['2026-09-03'], '14:00', '14:30'));
    }

    public function test_gerar_serie_cria_as_datas_e_nao_duplica_ao_rodar_de_novo(): void
    {
        $professor = Pessoa::factory()->create();
        $base = ['professor_id' => $professor->id, 'hora_inicio' => '10:00', 'hora_fim' => '10:30'];
        $regras = $this->regras('2026-09-01', '2026-09-30', [
            'dias_semana' => [2, 4],
            'ignorar_fins_de_semana' => true,
            'ignorar_feriados' => true,
        ]);

        $primeira = $this->servico->gerarSerie($base, $regras);

        // terças e quintas de setembro/2026: 1,3,8,10,15,17,22,24,29 (sem feriados nesses dias)
        $this->assertCount(9, $primeira['criadas']);
        $this->assertSame(9, Preceptoria::where('professor_id', $professor->id)->count());

        $segunda = $this->servico->gerarSerie($base, $regras);

        $this->assertSame([], $segunda['criadas']);
        $this->assertCount(9, $segunda['conflitos']);
        $this->assertSame(9, Preceptoria::where('professor_id', $professor->id)->count());
    }

    public function test_gerar_serie_recusa_janela_acima_do_limite(): void
    {
        $this->expectException(DomainException::class);

        $this->servico->gerarSerie(
            ['professor_id' => Pessoa::factory()->create()->id, 'hora_inicio' => '10:00'],
            $this->regras('2026-01-01', '2027-12-31'),
        );
    }

    public function test_reagendar_move_mantendo_a_duracao(): void
    {
        $preceptoria = Preceptoria::factory()->create(['data' => '2026-09-01', 'hora_inicio' => '14:00:00', 'hora_fim' => '14:45:00']);

        $resultado = $this->servico->reagendar($preceptoria, '2026-09-03T09:00:00');

        $this->assertTrue($resultado['ok']);
        $preceptoria->refresh();
        $this->assertSame('2026-09-03', $preceptoria->data->toDateString());
        $this->assertSame('09:00', $preceptoria->hora_inicio->format('H:i'));
        $this->assertSame('09:45', $preceptoria->hora_fim->format('H:i'));
    }

    public function test_reagendar_aceita_novo_fim_ao_esticar(): void
    {
        $preceptoria = Preceptoria::factory()->create(['data' => '2026-09-01', 'hora_inicio' => '14:00:00', 'hora_fim' => '14:30:00']);

        $resultado = $this->servico->reagendar($preceptoria, '2026-09-01T14:00:00', '2026-09-01T15:00:00');

        $this->assertTrue($resultado['ok']);
        $this->assertSame('15:00', $preceptoria->refresh()->hora_fim->format('H:i'));
    }

    public function test_reagendar_recusa_choque_com_outra_preceptoria_do_professor(): void
    {
        $professor = Pessoa::factory()->create();
        Preceptoria::factory()->create(['professor_id' => $professor->id, 'data' => '2026-09-03', 'hora_inicio' => '09:00:00', 'hora_fim' => '09:30:00']);
        $preceptoria = Preceptoria::factory()->create(['professor_id' => $professor->id, 'data' => '2026-09-01', 'hora_inicio' => '14:00:00', 'hora_fim' => '14:30:00']);

        $resultado = $this->servico->reagendar($preceptoria, '2026-09-03T09:15:00');

        $this->assertFalse($resultado['ok']);
        $this->assertStringContainsString('Conflito', $resultado['mensagem']);
        $this->assertSame('2026-09-01', $preceptoria->refresh()->data->toDateString());
    }

    public function test_reagendar_recusa_data_invalida_e_termino_em_outro_dia(): void
    {
        $preceptoria = Preceptoria::factory()->create(['data' => '2026-09-01', 'hora_inicio' => '14:00:00', 'hora_fim' => '14:30:00']);

        $this->assertFalse($this->servico->reagendar($preceptoria, '2026-09-01')['ok']);
        $this->assertFalse($this->servico->reagendar($preceptoria, 'lixo')['ok']);
        $this->assertFalse($this->servico->reagendar($preceptoria, '2026-09-02T23:45:00', '2026-09-03T00:15:00')['ok']);
        $this->assertFalse($this->servico->reagendar($preceptoria, '2026-09-02T10:00:00', '2026-09-02T09:00:00')['ok']);
    }

    public function test_reagendar_para_dia_especial_avisa_sem_impedir(): void
    {
        $preceptoria = Preceptoria::factory()->create(['data' => '2026-09-01', 'hora_inicio' => '14:00:00', 'hora_fim' => '14:30:00']);

        $sabado = $this->servico->reagendar($preceptoria, '2026-09-05T10:00:00');
        $this->assertTrue($sabado['ok']);
        $this->assertStringContainsString('sábado', $sabado['aviso']);

        $feriado = $this->servico->reagendar($preceptoria, '2026-09-07T10:00:00');
        $this->assertTrue($feriado['ok']);
        $this->assertStringContainsString('Independência', $feriado['aviso']);

        $comum = $this->servico->reagendar($preceptoria, '2026-09-08T10:00:00');
        $this->assertTrue($comum['ok']);
        $this->assertNull($comum['aviso']);
    }

    public function test_reagendar_preceptoria_agendada_avisa_os_envolvidos_com_o_horario_anterior(): void
    {
        Notification::fake();
        Carbon::setTestNow('2026-08-23 21:00:00');

        $professor = Pessoa::factory()->create(['email' => 'professor@example.com']);
        $aluno = Pessoa::factory()->create(['email' => 'aluno@example.com']);
        $matricula = Matricula::factory()->create(['pessoa_id' => $aluno->id]);

        $preceptoria = Preceptoria::factory()->create([
            'professor_id' => $professor->id,
            'matricula_id' => $matricula->id,
            'data' => '2026-08-24',
            'hora_inicio' => '07:15:00',
            'hora_fim' => '08:15:00',
        ]);

        $resultado = $this->servico->reagendar($preceptoria, '2026-08-25T09:00:00');

        $this->assertTrue($resultado['ok']);
        $this->assertSame(2, $resultado['notificados']);

        Notification::assertSentTo($professor, LembretePreceptoriaNotification::class, fn ($n) => $n->reagendadoDe === '24/08/2026 às 07:15');
        Notification::assertSentTo($aluno, LembretePreceptoriaNotification::class);
    }

    public function test_reagendar_nao_avisa_quando_livre_ou_quando_nao_pede(): void
    {
        Notification::fake();
        Carbon::setTestNow('2026-08-23 21:00:00');

        $professor = Pessoa::factory()->create(['email' => 'professor@example.com']);
        $livre = Preceptoria::factory()->create(['professor_id' => $professor->id, 'data' => '2026-08-24', 'hora_inicio' => '07:15:00', 'hora_fim' => '08:15:00']);

        $this->servico->reagendar($livre, '2026-08-25T09:00:00');

        $aluno = Pessoa::factory()->create(['email' => 'aluno@example.com']);
        $agendada = Preceptoria::factory()->create([
            'professor_id' => $professor->id,
            'matricula_id' => Matricula::factory()->create(['pessoa_id' => $aluno->id])->id,
            'data' => '2026-08-26',
            'hora_inicio' => '07:15:00',
            'hora_fim' => '08:15:00',
        ]);

        $this->servico->reagendar($agendada, '2026-08-27T09:00:00', null, notificar: false);

        Notification::assertNothingSent();
    }
}
