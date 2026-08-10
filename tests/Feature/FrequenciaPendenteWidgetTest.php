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
        $matricula = Matricula::factory()->create([
            'turma_id' => $turma->id,
            'pessoa_id' => $aluno->id,
        ]);

        $disciplina = Disciplina::factory()->create();

        // Cronograma Passado (pendente)
        $caPassado = CronogramaAula::factory()->create([
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'data' => now()->subDays(2)->format('Y-m-d'),
        ]);

        // Cronograma Futuro (não deve ser incluído)
        $caFuturo = CronogramaAula::factory()->create([
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'data' => now()->addDays(2)->format('Y-m-d'),
        ]);

        $testable = Livewire::test(FrequenciaPendenteWidget::class);

        $pendencias = $testable->instance()->getPendenciasAgrupadas();

        $this->assertTrue($pendencias->has(now()->subDays(2)->format('Y-m-d')));
        $this->assertFalse($pendencias->has(now()->addDays(2)->format('Y-m-d')));
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

        $testable->call('abrirModalLancamento', $dataHoje);

        $this->assertTrue($testable->get('showModal'));
        $this->assertEquals($dataHoje, $testable->get('dataSelecionada'));

        // Salva com estado padrão (presença para todos alunos da aula do dia)
        $testable->call('salvarFrequenciasDoDia');

        $this->assertFalse($testable->get('showModal'));

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
