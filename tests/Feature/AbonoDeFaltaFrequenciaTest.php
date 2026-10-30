<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Portal\Pages\Frequencia;
use App\Filament\Resources\FrequenciaEscolars\Pages\EditFrequenciaEscolar;
use App\Models\CronogramaAula;
use App\Models\Disciplina;
use App\Models\EtapaAvaliativa;
use App\Models\FrequenciaEscolar;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Turma;
use App\Models\User;
use App\Services\BoletimService;
use App\Services\Educacenso\SituacaoAlunoCensoService;
use App\Services\FaltasConselhoTutelarService;
use App\Services\FrequenciaAlunoService;
use App\Services\Painel\Indicadores\FrequenciaMedia30Dias;
use App\Services\RiscoEvasaoService;
use App\Support\Painel\ContextoPainel;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Justificar não é abonar: a falta justificada continua contando na frequência; só a abonada (que exige justificativa)
 * deixa de contar. O cenário tem 10 aulas, 6 presenças e 4 faltas: 1 injustificada, 1 só justificada e 2 abonadas.
 *
 * Sem abono a frequência seria 6/10 = 60% e as faltas seriam 4 (40%); com o abono, 8/10 = 80% e 2 faltas (20%).
 */
class AbonoDeFaltaFrequenciaTest extends TestCase
{
    use RefreshDatabase;

    private Turma $turma;

    private Disciplina $disciplina;

    private Matricula $matricula;

    private EtapaAvaliativa $etapa;

    protected function setUp(): void
    {
        parent::setUp();

        // "Hoje" é 22/10: a última aula (21/10) fica antes do dia de hoje. No SQLite a coluna `date` é gravada como
        // `2026-10-21 00:00:00` e não entra no `between` com `'2026-10-21'` (no MySQL entraria).
        $this->travelTo(Carbon::parse('2026-10-22 09:00:00'));

        $periodo = PeriodoLetivo::factory()->create();
        $this->turma = Turma::factory()->create(['periodo_letivo_id' => $periodo->id]);
        $this->disciplina = Disciplina::factory()->create();
        $this->matricula = Matricula::factory()->create([
            'pessoa_id' => Pessoa::factory()->create(['nome' => 'Ana Lima'])->id,
            'turma_id' => $this->turma->id,
            'situacao' => 'ativa',
        ]);
        $this->etapa = EtapaAvaliativa::create([
            'nome' => '1º Bimestre',
            'periodo_letivo_id' => $periodo->id,
            'turma_id' => $this->turma->id,
            'data_inicio' => '2026-10-01',
            'data_fim' => '2026-12-01',
        ]);

        // Aulas de 12 a 21/10: presenças nas 6 primeiras, depois as 4 faltas.
        $situacoes = [
            ['presente', false, false], ['presente', false, false], ['presente', false, false],
            ['presente', false, false], ['presente', false, false], ['presente', false, false],
            ['ausente', false, false],  // 18/10: injustificada
            ['ausente', true, false],   // 19/10: só justificada
            ['ausente', true, true],    // 20/10: justificada e abonada
            ['ausente', true, true],    // 21/10: justificada e abonada
        ];

        foreach ($situacoes as $i => [$situacao, $justificada, $abonada]) {
            $aula = CronogramaAula::factory()->create([
                'turma_id' => $this->turma->id,
                'disciplina_id' => $this->disciplina->id,
                'data' => Carbon::parse('2026-10-12')->addDays($i)->toDateString(),
            ]);

            FrequenciaEscolar::create([
                'matricula_id' => $this->matricula->id,
                'cronograma_aula_id' => $aula->id,
                'situacao' => $situacao,
                'justificada' => $justificada,
                'abonada' => $abonada,
                'motivo_justificativa' => $justificada ? 'Consulta' : null,
            ]);
        }
    }

    /**
     * Desfaz o abono do cenário (as abonadas continuam justificadas): o que existia antes da separação.
     */
    private function semAbono(): void
    {
        FrequenciaEscolar::query()->update(['abonada' => false]);
    }

    // --- Cálculos de frequência -------------------------------------------------------------------------------------------

    public function test_portal_conta_a_abonada_como_presenca_e_a_so_justificada_como_falta(): void
    {
        $resumo = app(FrequenciaAlunoService::class)->resumo($this->matricula);

        $this->assertSame(10, $resumo['total']);
        $this->assertSame(8, $resumo['presencas']);
        $this->assertSame(2, $resumo['faltas']);
        $this->assertSame(2, $resumo['abonadas']);
        $this->assertSame(80.0, $resumo['percentual']);

        $this->semAbono();

        $resumo = app(FrequenciaAlunoService::class)->resumo($this->matricula);
        $this->assertSame(60.0, $resumo['percentual'], 'só justificada continua contando');
        $this->assertSame(4, $resumo['faltas']);
        $this->assertSame(0, $resumo['abonadas']);
    }

    public function test_boletim_nao_lista_a_abonada_como_falta(): void
    {
        $dados = app(BoletimService::class)->getFrequenciaDisciplinaEtapa($this->disciplina->id, $this->matricula->id, $this->turma->id, $this->etapa);

        $this->assertEqualsWithDelta(80.0, $dados['frequencia'], 0.001);
        $this->assertSame(8, $dados['presencas_count']);
        $this->assertSame(['18/10', '19/10'], $dados['faltas_datas'], 'a injustificada e a só justificada; as abonadas ficam de fora');
    }

    public function test_conselho_tutelar_tira_a_abonada_de_todos_os_numeros(): void
    {
        $calculo = app(FaltasConselhoTutelarService::class)->calcularAtingimentoLimite($this->matricula);

        $this->assertSame(2, $calculo['faltas_totais']);
        $this->assertSame(1, $calculo['faltas_justificadas'], 'só justificada, sem abono');
        $this->assertSame(1, $calculo['faltas_injustificadas']);
        $this->assertSame(2, $calculo['faltas_abonadas']);
        $this->assertSame(
            $calculo['faltas_totais'],
            $calculo['faltas_justificadas'] + $calculo['faltas_injustificadas'],
            'justificadas e injustificadas fecham com o total'
        );
    }

    public function test_risco_de_evasao_usa_so_as_faltas_que_contam(): void
    {
        $pontos = fn (): int => collect(RiscoEvasaoService::detalhar($this->matricula->fresh()))->firstWhere('fator', 'Frequência')['pontos'];

        $this->assertSame(25, $pontos(), '2 faltas em 10 aulas = 20%');

        $this->semAbono();

        $this->assertSame(40, $pontos(), '4 faltas em 10 aulas = 40%');
    }

    public function test_indicador_do_painel_segue_a_mesma_contagem(): void
    {
        $contexto = new ContextoPainel(CarbonImmutable::parse('2026-10-22'));
        $indicador = new FrequenciaMedia30Dias;

        $this->assertSame(80.0, $indicador->valor($contexto));

        $this->semAbono();

        $this->assertSame(60.0, $indicador->valor($contexto));
    }

    public function test_situacao_do_aluno_no_censo_conta_a_abonada_como_presenca(): void
    {
        $servico = app(SituacaoAlunoCensoService::class);
        $frequencias = fn (): array => (new ReflectionMethod($servico, 'frequencias'))->invoke($servico, collect([$this->matricula->id]));

        $this->assertSame([$this->matricula->id => 80.0], $frequencias());

        $this->semAbono();

        $this->assertSame([$this->matricula->id => 60.0], $frequencias());
    }

    // --- A regra do cadastro da falta -------------------------------------------------------------------------------------

    public function test_abono_sem_justificativa_nao_vale(): void
    {
        $aula = CronogramaAula::factory()->create(['turma_id' => $this->turma->id, 'disciplina_id' => $this->disciplina->id, 'data' => '2026-10-21']);

        $falta = FrequenciaEscolar::create([
            'matricula_id' => $this->matricula->id,
            'cronograma_aula_id' => $aula->id,
            'situacao' => 'ausente',
            'justificada' => false,
            'abonada' => true,
        ]);

        $this->assertFalse($falta->fresh()->abonada, 'abonar exige justificar');

        // Tirar a justificativa de uma abonada tira o abono junto.
        $abonada = FrequenciaEscolar::query()->where('abonada', true)->firstOrFail();
        $abonada->update(['justificada' => false]);
        $this->assertFalse($abonada->fresh()->abonada);
    }

    public function test_scope_conta_como_falta_separa_justificada_de_abonada(): void
    {
        $this->assertSame(2, FrequenciaEscolar::query()->contaComoFalta()->count());
        $this->assertSame(3, FrequenciaEscolar::query()->where('justificada', true)->count());
        $this->assertSame(1, FrequenciaEscolar::query()->contaComoFalta()->where('justificada', true)->count());
    }

    // --- Telas ------------------------------------------------------------------------------------------------------------

    public function test_portal_mostra_o_estado_de_cada_falta_e_o_aviso_das_abonadas(): void
    {
        Role::firstOrCreate(['name' => 'responsavel', 'guard_name' => 'web']);
        $responsavel = Pessoa::factory()->create();
        $familia = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $responsavel->users()->save($familia);
        $familia->assignRole('responsavel');
        $familia->pessoas()->first()->alunos()->attach($this->matricula->pessoa_id);

        $pagina = Livewire::actingAs($familia)->test(Frequencia::class);

        $pagina->assertSee('Falta abonada')
            ->assertSee('Falta justificada')
            ->assertSee('2 faltas abonadas não contam na frequência');
    }

    public function test_tela_de_frequencia_escolar_justifica_e_abona_uma_falta_isolada(): void
    {
        $superAdmin = User::factory()->create(['activated_at' => now(), 'email_verified_at' => now()]);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->assignRole('super_admin');

        $injustificada = FrequenciaEscolar::query()->where('justificada', false)->where('situacao', 'ausente')->firstOrFail();

        // Só justificar: a falta continua contando.
        Livewire::actingAs($superAdmin)->test(EditFrequenciaEscolar::class, ['record' => $injustificada->id])
            ->fillForm(['justificada' => true, 'motivo_justificativa' => 'Consulta médica'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($injustificada->fresh()->justificada);
        $this->assertFalse($injustificada->fresh()->abonada);
        $this->assertSame(2, FrequenciaEscolar::query()->contaComoFalta()->count());

        // Abonar: sai da conta.
        Livewire::actingAs($superAdmin)->test(EditFrequenciaEscolar::class, ['record' => $injustificada->id])
            ->fillForm(['abonada' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($injustificada->fresh()->abonada);
        $this->assertSame(1, FrequenciaEscolar::query()->contaComoFalta()->count());
    }

    public function test_justificar_exige_o_motivo(): void
    {
        $superAdmin = User::factory()->create(['activated_at' => now(), 'email_verified_at' => now()]);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->assignRole('super_admin');

        $injustificada = FrequenciaEscolar::query()->where('justificada', false)->where('situacao', 'ausente')->firstOrFail();

        Livewire::actingAs($superAdmin)->test(EditFrequenciaEscolar::class, ['record' => $injustificada->id])
            ->fillForm(['justificada' => true, 'abonada' => true, 'motivo_justificativa' => ''])
            ->call('save')
            ->assertHasFormErrors(['motivo_justificativa']);

        $this->assertFalse($injustificada->fresh()->justificada);
    }

    public function test_tirar_a_justificativa_pela_tela_tira_o_abono_e_o_motivo(): void
    {
        $superAdmin = User::factory()->create(['activated_at' => now(), 'email_verified_at' => now()]);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->assignRole('super_admin');

        $abonada = FrequenciaEscolar::query()->where('abonada', true)->firstOrFail();

        Livewire::actingAs($superAdmin)->test(EditFrequenciaEscolar::class, ['record' => $abonada->id])
            ->fillForm(['justificada' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($abonada->fresh()->justificada);
        $this->assertFalse($abonada->fresh()->abonada);
        $this->assertNull($abonada->fresh()->motivo_justificativa);
    }
}
