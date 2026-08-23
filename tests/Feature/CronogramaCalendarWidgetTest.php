<?php

namespace Tests\Feature;

use App\Filament\Widgets\CronogramaCalendarWidget;
use App\Models\AlunoResponsavel;
use App\Models\Contrato;
use App\Models\CronogramaAula;
use App\Models\Disciplina;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\ResponsavelFinanceiro;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CronogramaCalendarWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_responsavel_visualiza_apenas_eventos_das_turmas_dos_seus_dependentes(): void
    {
        Role::firstOrCreate(['name' => 'responsavel', 'guard_name' => 'web']);

        $periodo = PeriodoLetivo::factory()->create();

        $turmaFilho = Turma::factory()->create([
            'periodo_letivo_id' => $periodo->id,
            'nome' => 'Turma do Filho',
        ]);

        $turmaOutra = Turma::factory()->create([
            'periodo_letivo_id' => $periodo->id,
            'nome' => 'Turma de Outro Aluno',
        ]);

        $disciplina = Disciplina::factory()->create(['nome' => 'Matemática']);
        $professor = Pessoa::factory()->create(['nome' => 'Prof. Carlos']);

        // Cria aula na turma do filho
        $aulaFilho = CronogramaAula::create([
            'turma_id' => $turmaFilho->id,
            'disciplina_id' => $disciplina->id,
            'pessoa_id' => $professor->id,
            'data' => now()->format('Y-m-d'),
            'hora_inicio' => '08:00',
            'hora_fim' => '08:50',
            'conteudo_ministrado' => 'Introdução à Álgebra',
        ]);

        // Cria aula na outra turma
        $aulaOutra = CronogramaAula::create([
            'turma_id' => $turmaOutra->id,
            'disciplina_id' => $disciplina->id,
            'pessoa_id' => $professor->id,
            'data' => now()->format('Y-m-d'),
            'hora_inicio' => '09:00',
            'hora_fim' => '09:50',
            'conteudo_ministrado' => 'Geometria',
        ]);

        // Cria responsável e aluno vinculado
        $responsavelPessoa = Pessoa::factory()->create(['nome' => 'Pai Teste']);
        $alunoPessoa = Pessoa::factory()->create(['nome' => 'Filho Teste']);

        $user = User::factory()->create([
            'activated_at' => now(),
            'email_verified_at' => now(),
        ]);
        $responsavelPessoa->users()->save($user);
        $user->assignRole('responsavel');

        AlunoResponsavel::create([
            'aluno_id' => $alunoPessoa->id,
            'responsavel_id' => $responsavelPessoa->id,
        ]);

        Matricula::factory()->create([
            'pessoa_id' => $alunoPessoa->id,
            'turma_id' => $turmaFilho->id,
            'periodo_letivo_id' => $periodo->id,
            'situacao' => 'ativa',
        ]);

        $this->actingAs($user);
        session(['active_role' => 'responsavel']);

        $widget = new CronogramaCalendarWidget;
        $events = $widget->getAllEvents();

        $eventIds = collect($events)->pluck('id')->toArray();

        $this->assertContains((string) $aulaFilho->id, $eventIds);
        $this->assertNotContains((string) $aulaOutra->id, $eventIds);
    }

    public function test_responsavel_financeiro_sem_aluno_responsavel_tambem_visualiza_eventos_do_contrato(): void
    {
        Role::firstOrCreate(['name' => 'responsavel', 'guard_name' => 'web']);

        $periodo = PeriodoLetivo::factory()->create();

        $turma = Turma::factory()->create([
            'periodo_letivo_id' => $periodo->id,
            'nome' => 'Turma Contrato',
        ]);

        $disciplina = Disciplina::factory()->create(['nome' => 'História']);
        $professor = Pessoa::factory()->create(['nome' => 'Prof. Ana']);

        $aula = CronogramaAula::create([
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'pessoa_id' => $professor->id,
            'data' => now()->format('Y-m-d'),
            'hora_inicio' => '10:00',
            'hora_fim' => '10:50',
            'conteudo_ministrado' => 'História do Brasil',
        ]);

        $responsavelPessoa = Pessoa::factory()->create(['nome' => 'Resp Financeiro']);
        $alunoPessoa = Pessoa::factory()->create(['nome' => 'Aluno do Contrato']);

        $user = User::factory()->create([
            'activated_at' => now(),
            'email_verified_at' => now(),
        ]);
        $responsavelPessoa->users()->save($user);
        $user->assignRole('responsavel');

        $matricula = Matricula::factory()->create([
            'pessoa_id' => $alunoPessoa->id,
            'turma_id' => $turma->id,
            'periodo_letivo_id' => $periodo->id,
            'situacao' => 'ativa',
        ]);

        $contrato = Contrato::create([
            'matricula_id' => $matricula->id,
            'valor_total' => 10000,
            'data_aceite' => now(),
        ]);

        ResponsavelFinanceiro::create([
            'pessoa_id' => $responsavelPessoa->id,
            'contrato_id' => $contrato->id,
            'percentual' => 100,
        ]);

        $this->actingAs($user);
        session(['active_role' => 'responsavel']);

        $widget = new CronogramaCalendarWidget;
        $events = $widget->getAllEvents();

        $eventIds = collect($events)->pluck('id')->toArray();

        $this->assertContains((string) $aula->id, $eventIds);
    }
}
