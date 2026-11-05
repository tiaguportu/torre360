<?php

namespace Tests\Feature;

use App\Enums\StatusReservaEspaco;
use App\Models\DiaNaoLetivo;
use App\Models\GradeHorario;
use App\Models\PeriodoLetivo;
use App\Models\ReservaEspaco;
use App\Models\Sala;
use App\Models\Turma;
use App\Services\ReservaEspacoConflitoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O espaço só está livre se não houver reserva (solicitada ou confirmada) nem aula da grade fixa que se cruze com a
 * faixa pedida; a grade só conta quando a turma tem aula naquele dia.
 */
class ReservaEspacoConflitoServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReservaEspacoConflitoService $servico;

    private Sala $sala;

    /** terça-feira, dentro do período letivo de 2026 */
    private Carbon $terca;

    protected function setUp(): void
    {
        parent::setUp();

        $this->servico = new ReservaEspacoConflitoService;
        $this->sala = Sala::factory()->create();
        $this->terca = Carbon::parse('2026-11-10');
    }

    private function reservar(StatusReservaEspaco $status, string $inicio = '14:00', string $fim = '15:00', ?Sala $sala = null, string $motivo = 'Palestra'): ReservaEspaco
    {
        return ReservaEspaco::factory()->create([
            'sala_id' => ($sala ?? $this->sala)->id,
            'data' => $this->terca->toDateString(),
            'hora_inicio' => $inicio,
            'hora_fim' => $fim,
            'status' => $status,
            'motivo' => $motivo,
        ]);
    }

    private function aulaFixa(Turma $turma, string $inicio = '14:00:00', string $fim = '15:00:00'): GradeHorario
    {
        return GradeHorario::factory()->create([
            'turma_id' => $turma->id,
            'sala_id' => $this->sala->id,
            'dia_semana' => $this->terca->dayOfWeek,
            'hora_inicio' => $inicio,
            'hora_fim' => $fim,
        ]);
    }

    public function test_espaco_sem_reserva_nem_aula_esta_livre(): void
    {
        $this->assertSame([], $this->servico->conflitos($this->sala->id, $this->terca, '14:00', '15:00'));
    }

    public function test_reserva_confirmada_que_se_cruza_e_conflito_com_o_motivo(): void
    {
        $this->reservar(StatusReservaEspaco::Confirmada, '14:00', '15:00', motivo: 'Ensaio de formatura');

        $mensagens = $this->servico->conflitos($this->sala->id, $this->terca, '14:30', '15:30');

        $this->assertCount(1, $mensagens);
        $this->assertStringContainsString('14:00 às 15:00', $mensagens[0]);
        $this->assertStringContainsString('Ensaio de formatura', $mensagens[0]);
        $this->assertStringNotContainsString('aguardando aprovação', $mensagens[0]);
    }

    public function test_reserva_apenas_solicitada_tambem_ocupa_e_avisa_que_aguarda_aprovacao(): void
    {
        $this->reservar(StatusReservaEspaco::Solicitada);

        $mensagens = $this->servico->conflitos($this->sala->id, $this->terca, '14:00', '15:00');

        $this->assertCount(1, $mensagens);
        $this->assertStringContainsString('(aguardando aprovação)', $mensagens[0]);
    }

    public function test_reserva_cancelada_nao_ocupa(): void
    {
        $this->reservar(StatusReservaEspaco::Cancelada);

        $this->assertSame([], $this->servico->conflitos($this->sala->id, $this->terca, '14:00', '15:00'));
    }

    public function test_faixas_que_apenas_se_tocam_nao_conflitam(): void
    {
        $this->reservar(StatusReservaEspaco::Confirmada, '14:00', '15:00');

        $this->assertSame([], $this->servico->conflitos($this->sala->id, $this->terca, '15:00', '16:00'));
        $this->assertSame([], $this->servico->conflitos($this->sala->id, $this->terca, '13:00', '14:00'));
    }

    public function test_reserva_de_outra_sala_ou_de_outro_dia_nao_conflita(): void
    {
        $this->reservar(StatusReservaEspaco::Confirmada, sala: Sala::factory()->create());

        $this->assertSame([], $this->servico->conflitos($this->sala->id, $this->terca, '14:00', '15:00'));

        $this->reservar(StatusReservaEspaco::Confirmada);
        $this->assertSame([], $this->servico->conflitos($this->sala->id, $this->terca->copy()->addDay(), '14:00', '15:00'));
    }

    public function test_ao_editar_a_propria_reserva_ela_nao_conflita_consigo_mesma(): void
    {
        $propria = $this->reservar(StatusReservaEspaco::Confirmada);

        $this->assertCount(1, $this->servico->conflitos($this->sala->id, $this->terca, '14:00', '15:00'));
        $this->assertSame([], $this->servico->conflitos($this->sala->id, $this->terca, '14:00', '15:00', $propria->id));
    }

    public function test_aula_da_grade_fixa_naquele_dia_da_semana_ocupa_a_sala(): void
    {
        $turma = Turma::factory()->create(['nome' => '5º Ano A']);
        $this->aulaFixa($turma);

        $mensagens = $this->servico->conflitos($this->sala->id, $this->terca, '14:30', '15:30');

        $this->assertCount(1, $mensagens);
        $this->assertStringContainsString('A grade horária fixa ocupa a sala das 14:00 às 15:00', $mensagens[0]);
        $this->assertStringContainsString('5º Ano A', $mensagens[0]);
    }

    public function test_aula_da_grade_em_outro_horario_ou_outro_dia_da_semana_nao_ocupa(): void
    {
        $turma = Turma::factory()->create();
        $this->aulaFixa($turma, '08:00:00', '08:50:00');

        $this->assertSame([], $this->servico->conflitos($this->sala->id, $this->terca, '14:00', '15:00'));
        $this->assertSame([], $this->servico->conflitos($this->sala->id, $this->terca->copy()->addDay(), '08:00', '08:50'), 'quarta-feira');
    }

    public function test_fora_do_periodo_letivo_da_turma_a_grade_nao_ocupa(): void
    {
        $periodo = PeriodoLetivo::factory()->create(['nome' => '2027', 'data_inicio' => '2027-02-01', 'data_fim' => '2027-12-15']);
        $turma = Turma::factory()->create(['periodo_letivo_id' => $periodo->id]);
        $this->aulaFixa($turma);

        $this->assertSame([], $this->servico->conflitos($this->sala->id, $this->terca, '14:00', '15:00'), '10/11/2026 é antes do período da turma');
        $this->assertCount(1, $this->servico->conflitos($this->sala->id, Carbon::parse('2027-11-09'), '14:00', '15:00'), '09/11/2027 é terça-feira dentro do período');
    }

    public function test_dia_nao_letivo_libera_a_sala(): void
    {
        $turma = Turma::factory()->create();
        $this->aulaFixa($turma);

        $this->assertCount(1, $this->servico->conflitos($this->sala->id, $this->terca, '14:00', '15:00'));

        DiaNaoLetivo::create(['periodo_letivo_id' => $turma->periodo_letivo_id, 'data' => $this->terca->toDateString(), 'descricao' => 'Recesso', 'flag_ativo' => true]);

        $this->assertSame([], $this->servico->conflitos($this->sala->id, $this->terca, '14:00', '15:00'));
    }

    public function test_dia_nao_letivo_inativo_nao_libera_a_sala(): void
    {
        $turma = Turma::factory()->create();
        $this->aulaFixa($turma);
        DiaNaoLetivo::create(['periodo_letivo_id' => $turma->periodo_letivo_id, 'data' => $this->terca->toDateString(), 'descricao' => 'Recesso desfeito', 'flag_ativo' => false]);

        $this->assertCount(1, $this->servico->conflitos($this->sala->id, $this->terca, '14:00', '15:00'));
    }

    public function test_turma_inexistente_conta_como_tendo_aula_e_hora_e_normalizada(): void
    {
        $this->assertTrue($this->servico->turmaTemAulaNaData(null, $this->terca));
        $this->assertSame('14:00:00', ReservaEspacoConflitoService::normalizarHora('14:00'));
        $this->assertSame('14:05:00', ReservaEspacoConflitoService::normalizarHora('2026-11-10 14:05:00'));
    }
}
