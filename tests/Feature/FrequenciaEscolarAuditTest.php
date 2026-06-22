<?php

namespace Tests\Feature;

use App\Models\CronogramaAula;
use App\Models\Disciplina;
use App\Models\FrequenciaEscolar;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class FrequenciaEscolarAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Matricula $matricula;

    protected CronogramaAula $cronogramaAula;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $turma = Turma::factory()->create(['nome' => 'Turma 101']);
        $disciplina = Disciplina::factory()->create(['nome' => 'Matemática']);
        $aluno = Pessoa::factory()->create(['nome' => 'João Silva']);

        $this->matricula = Matricula::factory()->create([
            'turma_id' => $turma->id,
            'pessoa_id' => $aluno->id,
        ]);

        $this->cronogramaAula = CronogramaAula::factory()->create([
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'data' => '2026-06-22',
        ]);
    }

    public function test_deve_gravar_audit_log_ao_criar_frequencia_escolar(): void
    {
        $frequencia = FrequenciaEscolar::create([
            'matricula_id' => $this->matricula->id,
            'cronograma_aula_id' => $this->cronogramaAula->id,
            'situacao' => 'presente',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => FrequenciaEscolar::class,
            'subject_id' => $frequencia->id,
            'event' => 'created',
            'log_name' => 'frequencia_escolar',
            'causer_id' => $this->user->id,
        ]);

        $activity = Activity::where('subject_type', FrequenciaEscolar::class)
            ->where('subject_id', $frequencia->id)
            ->first();

        $this->assertStringContainsString("Registrada frequência do aluno João Silva como 'Presente' na Matemática da turma Turma 101 em 22/06/2026.", $activity->description);
    }

    public function test_deve_gravar_audit_log_ao_atualizar_frequencia_escolar(): void
    {
        $frequencia = FrequenciaEscolar::create([
            'matricula_id' => $this->matricula->id,
            'cronograma_aula_id' => $this->cronogramaAula->id,
            'situacao' => 'ausente',
        ]);

        $frequencia->update([
            'situacao' => 'presente',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => FrequenciaEscolar::class,
            'subject_id' => $frequencia->id,
            'event' => 'updated',
            'log_name' => 'frequencia_escolar',
            'causer_id' => $this->user->id,
        ]);

        $activity = Activity::where('subject_type', FrequenciaEscolar::class)
            ->where('subject_id', $frequencia->id)
            ->where('event', 'updated')
            ->first();

        $this->assertStringContainsString("Atualizada frequência do aluno João Silva para 'Presente' na Matemática da turma Turma 101 em 22/06/2026.", $activity->description);
        $this->assertEquals('ausente', $activity->properties['old']['situacao']);
        $this->assertEquals('presente', $activity->properties['attributes']['situacao']);
    }

    public function test_deve_gravar_audit_log_ao_deletar_frequencia_escolar(): void
    {
        $frequencia = FrequenciaEscolar::create([
            'matricula_id' => $this->matricula->id,
            'cronograma_aula_id' => $this->cronogramaAula->id,
            'situacao' => 'presente',
        ]);

        $frequencia->delete();

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => FrequenciaEscolar::class,
            'subject_id' => $frequencia->id,
            'event' => 'deleted',
            'log_name' => 'frequencia_escolar',
            'causer_id' => $this->user->id,
        ]);

        $activity = Activity::where('subject_type', FrequenciaEscolar::class)
            ->where('subject_id', $frequencia->id)
            ->where('event', 'deleted')
            ->first();

        $this->assertStringContainsString('Removido registro de frequência do aluno João Silva na Matemática da turma Turma 101 em 22/06/2026.', $activity->description);
    }
}
