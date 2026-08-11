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

class ProfessorRegenteChamadaTest extends TestCase
{
    use RefreshDatabase;

    public function test_professor_regente_consegue_visualizar_e_lancar_chamada_em_aula_de_outro_professor_da_mesma_turma(): void
    {
        $professorRegentePessoa = Pessoa::factory()->create();
        $userRegente = User::factory()->create([
            'activated_at' => now()->subDay(),
            'email_verified_at' => now(),
        ]);
        $userRegente->pessoas()->attach($professorRegentePessoa->id);

        $outraProfessoraPessoa = Pessoa::factory()->create();

        // Turma onde a regente é a professoraRegentePessoa (professor_conselheiro_id)
        $turma = Turma::factory()->create([
            'professor_conselheiro_id' => $professorRegentePessoa->id,
        ]);

        $aluno = Pessoa::factory()->create();
        Matricula::factory()->create([
            'turma_id' => $turma->id,
            'pessoa_id' => $aluno->id,
            'data_ativacao' => now()->subDays(5)->format('Y-m-d'),
        ]);

        $disciplina = Disciplina::factory()->create();

        // Aula criada no nome da outra professora
        $aulaOutraProfessora = CronogramaAula::factory()->create([
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'pessoa_id' => $outraProfessoraPessoa->id,
            'data' => now()->subDays(1)->format('Y-m-d'),
        ]);

        // Autentica como a professora regente no perfil de professor
        $this->actingAs($userRegente);
        session(['active_role' => 'professor']);

        $widget = Livewire::test(FrequenciaPendenteWidget::class);
        $widget->assertSuccessful();

        $pendencias = $widget->instance()->getPendenciasAgrupadas();
        $this->assertTrue($pendencias->has(now()->subDays(1)->format('Y-m-d')));
    }
}
