<?php

namespace Tests\Feature;

use App\Filament\Resources\Turmas\Pages\EditTurma;
use App\Filament\Resources\Turmas\Pages\ListTurmas;
use App\Filament\Resources\Turmas\RelationManagers\GradeHorariosRelationManager;
use App\Models\CronogramaAula;
use App\Models\DiaNaoLetivo;
use App\Models\Disciplina;
use App\Models\GradeHorario;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Sala;
use App\Models\Turma;
use App\Models\User;
use App\Services\GradeHorarioConflitoService;
use App\Services\GradeHorarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GradeHorarioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['activated_at' => now()]);
        $user->assignRole('super_admin');

        return $user;
    }

    // ─── Conflitos ──────────────────────────────────────────────────

    public function test_detecta_conflito_de_turma(): void
    {
        $turma = Turma::factory()->create();
        GradeHorario::factory()->create([
            'turma_id' => $turma->id,
            'dia_semana' => 1,
            'hora_inicio' => '08:00:00',
            'hora_fim' => '09:00:00',
        ]);

        $novo = new GradeHorario([
            'turma_id' => $turma->id,
            'disciplina_id' => Disciplina::factory()->create()->id,
            'dia_semana' => 1,
            'hora_inicio' => '08:30:00',
            'hora_fim' => '09:30:00',
        ]);

        $conflitos = app(GradeHorarioConflitoService::class)->conflitos($novo);

        $this->assertNotEmpty($conflitos);
    }

    public function test_sem_conflito_quando_horarios_nao_se_sobrepoem(): void
    {
        $turma = Turma::factory()->create();
        GradeHorario::factory()->create([
            'turma_id' => $turma->id,
            'dia_semana' => 1,
            'hora_inicio' => '08:00:00',
            'hora_fim' => '09:00:00',
        ]);

        $novo = new GradeHorario([
            'turma_id' => $turma->id,
            'disciplina_id' => Disciplina::factory()->create()->id,
            'dia_semana' => 1,
            'hora_inicio' => '09:00:00',
            'hora_fim' => '10:00:00',
        ]);

        $this->assertSame([], app(GradeHorarioConflitoService::class)->conflitos($novo));
    }

    public function test_sem_conflito_em_dia_da_semana_diferente(): void
    {
        $turma = Turma::factory()->create();
        GradeHorario::factory()->create([
            'turma_id' => $turma->id,
            'dia_semana' => 1,
            'hora_inicio' => '08:00:00',
            'hora_fim' => '09:00:00',
        ]);

        $novo = new GradeHorario([
            'turma_id' => $turma->id,
            'disciplina_id' => Disciplina::factory()->create()->id,
            'dia_semana' => 2,
            'hora_inicio' => '08:00:00',
            'hora_fim' => '09:00:00',
        ]);

        $this->assertSame([], app(GradeHorarioConflitoService::class)->conflitos($novo));
    }

    public function test_detecta_conflito_de_professor_entre_turmas_diferentes(): void
    {
        $professor = Pessoa::factory()->create();
        GradeHorario::factory()->create([
            'professor_id' => $professor->id,
            'dia_semana' => 3,
            'hora_inicio' => '10:00:00',
            'hora_fim' => '11:00:00',
        ]);

        $novo = new GradeHorario([
            'turma_id' => Turma::factory()->create()->id,
            'disciplina_id' => Disciplina::factory()->create()->id,
            'professor_id' => $professor->id,
            'dia_semana' => 3,
            'hora_inicio' => '10:30:00',
            'hora_fim' => '11:30:00',
        ]);

        $this->assertNotEmpty(app(GradeHorarioConflitoService::class)->conflitos($novo));
    }

    public function test_detecta_conflito_de_sala(): void
    {
        $sala = Sala::factory()->create();
        GradeHorario::factory()->create([
            'sala_id' => $sala->id,
            'dia_semana' => 4,
            'hora_inicio' => '14:00:00',
            'hora_fim' => '15:00:00',
        ]);

        $novo = new GradeHorario([
            'turma_id' => Turma::factory()->create()->id,
            'disciplina_id' => Disciplina::factory()->create()->id,
            'sala_id' => $sala->id,
            'dia_semana' => 4,
            'hora_inicio' => '14:30:00',
            'hora_fim' => '15:30:00',
        ]);

        $this->assertNotEmpty(app(GradeHorarioConflitoService::class)->conflitos($novo));
    }

    public function test_editar_o_proprio_registro_nao_conflita_consigo_mesmo(): void
    {
        $turma = Turma::factory()->create();
        $grade = GradeHorario::factory()->create([
            'turma_id' => $turma->id,
            'dia_semana' => 1,
            'hora_inicio' => '08:00:00',
            'hora_fim' => '09:00:00',
        ]);

        $conflitos = app(GradeHorarioConflitoService::class)->conflitos($grade);

        $this->assertSame([], $conflitos);
    }

    // ─── Relation manager (halt ao salvar com conflito) ────────────

    public function test_relation_manager_bloqueia_criacao_com_conflito_de_turma(): void
    {
        $turma = Turma::factory()->create();
        GradeHorario::factory()->create([
            'turma_id' => $turma->id,
            'dia_semana' => 1,
            'hora_inicio' => '08:00:00',
            'hora_fim' => '09:00:00',
        ]);
        $disciplina = Disciplina::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(GradeHorariosRelationManager::class, ['ownerRecord' => $turma, 'pageClass' => EditTurma::class])
            ->callTableAction('create', data: [
                'disciplina_id' => $disciplina->id,
                'dia_semana' => 1,
                'hora_inicio' => '08:30',
                'hora_fim' => '09:30',
            ])
            ->assertNotified();

        $this->assertSame(1, GradeHorario::where('turma_id', $turma->id)->count());
    }

    public function test_relation_manager_permite_criacao_sem_conflito(): void
    {
        $turma = Turma::factory()->create();
        $disciplina = Disciplina::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(GradeHorariosRelationManager::class, ['ownerRecord' => $turma, 'pageClass' => EditTurma::class])
            ->callTableAction('create', data: [
                'disciplina_id' => $disciplina->id,
                'dia_semana' => 1,
                'hora_inicio' => '08:00',
                'hora_fim' => '08:50',
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(1, GradeHorario::where('turma_id', $turma->id)->count());
    }

    // ─── Geração do cronograma ──────────────────────────────────────

    public function test_gerar_cronograma_cria_aulas_nas_datas_corretas_e_pula_dia_nao_letivo(): void
    {
        $periodo = PeriodoLetivo::factory()->create([
            'data_inicio' => '2026-02-02', // segunda-feira
            'data_fim' => '2026-02-15',
        ]);
        $turma = Turma::factory()->create(['periodo_letivo_id' => $periodo->id]);
        $disciplina = Disciplina::factory()->create();
        $professor = Pessoa::factory()->create();

        GradeHorario::factory()->create([
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'professor_id' => $professor->id,
            'dia_semana' => 1, // segunda-feira
            'hora_inicio' => '08:00:00',
            'hora_fim' => '08:50:00',
        ]);

        // 09/02/2026 é segunda-feira dentro do período: marcar como não letivo.
        DiaNaoLetivo::create([
            'periodo_letivo_id' => $periodo->id,
            'data' => '2026-02-09',
            'descricao' => 'Recesso Teste',
            'flag_ativo' => true,
        ]);

        $total = app(GradeHorarioService::class)->gerarCronograma($turma);

        // Segundas no período: 02/02 e 09/02. A de 09/02 é pulada.
        $this->assertSame(1, $total);
        $this->assertDatabaseHas('cronograma_aula', [
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'pessoa_id' => $professor->id,
            'data' => '2026-02-02',
        ]);
        $this->assertDatabaseMissing('cronograma_aula', [
            'turma_id' => $turma->id,
            'data' => '2026-02-09',
        ]);
    }

    public function test_gerar_cronograma_nao_duplica_ao_rodar_duas_vezes(): void
    {
        $periodo = PeriodoLetivo::factory()->create(['data_inicio' => '2026-03-02', 'data_fim' => '2026-03-08']);
        $turma = Turma::factory()->create(['periodo_letivo_id' => $periodo->id]);

        GradeHorario::factory()->create([
            'turma_id' => $turma->id,
            'dia_semana' => 1,
            'hora_inicio' => '08:00:00',
            'hora_fim' => '08:50:00',
        ]);

        $service = app(GradeHorarioService::class);
        $primeira = $service->gerarCronograma($turma);
        $segunda = $service->gerarCronograma($turma);

        $this->assertSame(1, $primeira);
        $this->assertSame(0, $segunda);
        $this->assertSame(1, CronogramaAula::where('turma_id', $turma->id)->count());
    }

    public function test_gerar_cronograma_sem_grade_horaria_nao_cria_nada(): void
    {
        $turma = Turma::factory()->create(['periodo_letivo_id' => PeriodoLetivo::factory()->create()->id]);

        $this->assertSame(0, app(GradeHorarioService::class)->gerarCronograma($turma));
    }

    public function test_acao_gerar_cronograma_na_tabela_de_turmas(): void
    {
        $periodo = PeriodoLetivo::factory()->create(['data_inicio' => '2026-04-06', 'data_fim' => '2026-04-12']);
        $turma = Turma::factory()->create(['periodo_letivo_id' => $periodo->id, 'nome' => 'Turma Cronograma']);
        GradeHorario::factory()->create([
            'turma_id' => $turma->id,
            'dia_semana' => 1,
            'hora_inicio' => '08:00:00',
            'hora_fim' => '08:50:00',
        ]);

        Livewire::actingAs($this->admin())
            ->test(ListTurmas::class)
            ->callTableAction('gerarCronograma', $turma)
            ->assertNotified();

        $this->assertDatabaseHas('cronograma_aula', ['turma_id' => $turma->id, 'data' => '2026-04-06']);
    }

    public function test_acao_gerar_cronograma_oculta_quando_turma_nao_tem_grade(): void
    {
        $turma = Turma::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ListTurmas::class)
            ->assertTableActionHidden('gerarCronograma', $turma);
    }
}
