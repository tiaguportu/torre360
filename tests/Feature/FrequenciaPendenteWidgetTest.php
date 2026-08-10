<?php

namespace Tests\Feature;

use App\Filament\Widgets\FrequenciaPendenteWidget;
use App\Models\CronogramaAula;
use App\Models\Disciplina;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FrequenciaPendenteWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_widget_exibe_pendencias_para_datas_hoje_ou_anteriores(): void
    {
        $user = User::factory()->create([
            'activated_at' => now()->subDay(),
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $turma = Turma::factory()->create();
        $aluno = Pessoa::factory()->create();
        Matricula::factory()->create([
            'turma_id' => $turma->id,
            'pessoa_id' => $aluno->id,
        ]);

        $disciplina = Disciplina::factory()->create();

        // Cronograma Passado (pendente)
        CronogramaAula::factory()->create([
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'data' => now()->subDays(2)->format('Y-m-d'),
        ]);

        // Cronograma Futuro (não deve ser incluído)
        CronogramaAula::factory()->create([
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'data' => now()->addDays(2)->format('Y-m-d'),
        ]);

        $testable = Livewire::test(FrequenciaPendenteWidget::class);

        $pendencias = $testable->instance()->getPendenciasAgrupadas();

        $this->assertTrue($pendencias->has(now()->subDays(2)->format('Y-m-d')));
        $this->assertFalse($pendencias->has(now()->addDays(2)->format('Y-m-d')));
    }

    public function test_professor_so_visualiza_suas_proprias_pendencias(): void
    {
        Role::firstOrCreate(['name' => 'professor']);

        $profPessoa1 = Pessoa::factory()->create(['nome' => 'Professor Um']);
        $profUser1 = User::factory()->create([
            'activated_at' => now()->subDay(),
            'email_verified_at' => now(),
        ]);
        $profUser1->pessoas()->attach($profPessoa1->id);
        $profUser1->assignRole('professor');

        $profPessoa2 = Pessoa::factory()->create(['nome' => 'Professor Dois']);

        $turma = Turma::factory()->create();
        $aluno = Pessoa::factory()->create();
        Matricula::factory()->create([
            'turma_id' => $turma->id,
            'pessoa_id' => $aluno->id,
        ]);
        $disciplina = Disciplina::factory()->create();

        $dataHoje = now()->toDateString();

        // Aula do Prof 1
        $caProf1 = CronogramaAula::factory()->create([
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'pessoa_id' => $profPessoa1->id,
            'data' => $dataHoje,
        ]);

        // Aula do Prof 2
        CronogramaAula::factory()->create([
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'pessoa_id' => $profPessoa2->id,
            'data' => $dataHoje,
        ]);

        $this->actingAs($profUser1);

        $testable = Livewire::test(FrequenciaPendenteWidget::class);
        $pendencias = $testable->instance()->getPendenciasAgrupadas();

        $this->assertTrue($pendencias->has($dataHoje));
        $aulasDoDia = $pendencias->get($dataHoje);

        $this->assertCount(1, $aulasDoDia);
        $this->assertEquals($caProf1->id, $aulasDoDia->first()->id);
    }

    public function test_lancamento_em_lote_do_dia_salva_frequencias_com_sucesso(): void
    {
        $user = User::factory()->create([
            'activated_at' => now()->subDay(),
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        $turma = Turma::factory()->create();
        $aluno1 = Pessoa::factory()->create();
        $aluno2 = Pessoa::factory()->create();

        $m1 = Matricula::factory()->create(['turma_id' => $turma->id, 'pessoa_id' => $aluno1->id]);
        $m2 = Matricula::factory()->create(['turma_id' => $turma->id, 'pessoa_id' => $aluno2->id]);

        $disciplina = Disciplina::factory()->create();
        $dataHoje = now()->toDateString();

        $ca = CronogramaAula::factory()->create([
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'data' => $dataHoje,
        ]);

        $testable = Livewire::test(FrequenciaPendenteWidget::class);

        $testable->mountAction('lancarChamadaDia', ['data' => $dataHoje])
            ->callMountedAction();

        $this->assertDatabaseHas('frequencia_escolar', [
            'cronograma_aula_id' => $ca->id,
            'matricula_id' => $m1->id,
            'situacao' => 'presente',
        ]);

        $this->assertDatabaseHas('frequencia_escolar', [
            'cronograma_aula_id' => $ca->id,
            'matricula_id' => $m2->id,
            'situacao' => 'presente',
        ]);
    }
}
