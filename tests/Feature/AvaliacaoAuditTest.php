<?php

namespace Tests\Feature;

use App\Models\Avaliacao;
use App\Models\CategoriaAvaliacao;
use App\Models\Disciplina;
use App\Models\EtapaAvaliativa;
use App\Models\PeriodoLetivo;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AvaliacaoAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Turma $turma;

    protected Disciplina $disciplina;

    protected EtapaAvaliativa $etapa;

    protected CategoriaAvaliacao $categoria;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->turma = Turma::factory()->create(['nome' => 'Turma 101']);
        $this->disciplina = Disciplina::factory()->create(['nome' => 'Matemática']);

        $periodo = PeriodoLetivo::factory()->create();

        $this->etapa = EtapaAvaliativa::create([
            'nome' => '1º Bimestre',
            'turma_id' => $this->turma->id,
            'periodo_letivo_id' => $periodo->id,
            'data_inicio' => '2026-01-01',
            'data_fim' => '2026-03-31',
        ]);

        $this->categoria = CategoriaAvaliacao::factory()->create(['nome' => 'Prova 1']);
    }

    private function criarAvaliacao(): Avaliacao
    {
        return Avaliacao::create([
            'turma_id' => $this->turma->id,
            'disciplina_id' => $this->disciplina->id,
            'etapa_avaliativa_id' => $this->etapa->id,
            'categoria_avaliacao_id' => $this->categoria->id,
            'professor_id' => null,
            'data_prevista' => now(),
            'data_ocorrencia' => now(),
            'data_limite_lancamento' => now()->addDays(15),
            'nota_maxima' => 10,
            'peso_etapa_avaliativa' => 1,
        ]);
    }

    public function test_deve_gravar_audit_log_ao_criar_avaliacao(): void
    {
        $avaliacao = $this->criarAvaliacao();

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Avaliacao::class,
            'subject_id' => $avaliacao->id,
            'event' => 'created',
            'log_name' => 'avaliacao',
            'causer_id' => $this->user->id,
        ]);

        $activity = Activity::where('subject_type', Avaliacao::class)
            ->where('subject_id', $avaliacao->id)
            ->where('event', 'created')
            ->first();

        $this->assertStringContainsString('Criada avaliação: Prova 1 - Turma 101 - Matemática - 1º Bimestre.', $activity->description);
    }

    public function test_deve_gravar_audit_log_ao_atualizar_avaliacao(): void
    {
        $avaliacao = $this->criarAvaliacao();

        $avaliacao->update(['nota_maxima' => 8]);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Avaliacao::class,
            'subject_id' => $avaliacao->id,
            'event' => 'updated',
            'log_name' => 'avaliacao',
            'causer_id' => $this->user->id,
        ]);

        $activity = Activity::where('subject_type', Avaliacao::class)
            ->where('subject_id', $avaliacao->id)
            ->where('event', 'updated')
            ->first();

        $this->assertStringContainsString('Atualizada avaliação: Prova 1 - Turma 101 - Matemática - 1º Bimestre.', $activity->description);
        $this->assertEquals(10, $activity->properties['old']['nota_maxima']);
        $this->assertEquals(8, $activity->properties['attributes']['nota_maxima']);
    }

    public function test_deve_gravar_audit_log_ao_deletar_avaliacao(): void
    {
        $avaliacao = $this->criarAvaliacao();

        $avaliacao->delete();

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Avaliacao::class,
            'subject_id' => $avaliacao->id,
            'event' => 'deleted',
            'log_name' => 'avaliacao',
            'causer_id' => $this->user->id,
        ]);

        $activity = Activity::where('subject_type', Avaliacao::class)
            ->where('subject_id', $avaliacao->id)
            ->where('event', 'deleted')
            ->first();

        $this->assertStringContainsString('Excluída avaliação: Prova 1 - Turma 101 - Matemática - 1º Bimestre.', $activity->description);
    }
}
