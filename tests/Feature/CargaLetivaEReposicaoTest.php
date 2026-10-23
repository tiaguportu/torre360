<?php

namespace Tests\Feature;

use App\Filament\Resources\ReposicaoAulas\Pages\ListReposicaoAulas;
use App\Models\CronogramaAula;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\ReposicaoAula;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\Turno;
use App\Models\Unidade;
use App\Models\User;
use App\Services\CargaLetivaService;
use Database\Factories\PeriodoLetivoFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CargaLetivaEReposicaoTest extends TestCase
{
    use RefreshDatabase;

    private function autenticarComoAdmin(): User
    {
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $perms = [
            'ViewAny:ReposicaoAula',
            'View:ReposicaoAula',
            'Create:ReposicaoAula',
            'Update:ReposicaoAula',
            'Delete:ReposicaoAula',
        ];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        $role->syncPermissions(Permission::all());

        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function criarCenarioTurma(string $tipoCurso = 'Fundamental'): array
    {
        $unidade = Unidade::create(['nome' => 'Unidade Teste', 'situacao_funcionamento' => '1']);
        $curso = Curso::create([
            'unidade_id' => $unidade->id,
            'nome_externo' => $tipoCurso === 'Médio' ? 'Ensino Médio' : 'Ensino Fundamental',
            'nome_interno' => $tipoCurso === 'Médio' ? 'EM' : 'EF',
        ]);
        $serie = Serie::create(['curso_id' => $curso->id, 'nome' => '1ª Série', 'sistema_avaliacao' => 'Nota']);
        $turno = Turno::create(['nome' => 'Manhã', 'hora_inicio' => '07:30:00', 'hora_fim' => '12:00:00']);
        $periodo = PeriodoLetivo::create(['nome' => '2026', 'ano' => 2026, 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);

        $turma = Turma::create([
            'periodo_letivo_id' => PeriodoLetivoFactory::idPadrao(),
            'nome' => 'Turma 101',
            'serie_id' => $serie->id,
            'turno_id' => $turno->id,
            'vagas_maximas' => 30,
        ]);

        $disciplina = Disciplina::create(['nome' => 'Matemática', 'sigla' => 'MAT']);
        $professor = Pessoa::create(['nome' => 'Professor Teste', 'data_nascimento' => '1985-04-12']);

        return [$turma, $disciplina, $professor];
    }

    public function test_obter_minimos_legais_retorna_parametros_ldb_corretos(): void
    {
        $service = app(CargaLetivaService::class);

        [$turmaFundamental] = $this->criarCenarioTurma('Fundamental');
        $minimosFund = $service->obterMinimosLegais($turmaFundamental);
        $this->assertEquals(200, $minimosFund['minimo_dias']);
        $this->assertEquals(800, $minimosFund['minimo_horas']);

        [$turmaMedio] = $this->criarCenarioTurma('Médio');
        $minimosMedio = $service->obterMinimosLegais($turmaMedio);
        $this->assertEquals(200, $minimosMedio['minimo_dias']);
        $this->assertEquals(1000, $minimosMedio['minimo_horas']);
    }

    public function test_apurar_turma_calcula_dias_horas_e_semaforo_corretamente(): void
    {
        $service = app(CargaLetivaService::class);
        [$turma, $disciplina, $professor] = $this->criarCenarioTurma('Fundamental');

        // Cria 2 aulas em dias diferentes com 60 minutos de duração cada (08:00 às 09:00)
        CronogramaAula::create([
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'pessoa_id' => $professor->id,
            'data' => '2026-03-02',
            'hora_inicio' => '08:00:00',
            'hora_fim' => '09:00:00',
            'conteudo_ministrado' => 'Introdução à Álgebra',
        ]);

        CronogramaAula::create([
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'pessoa_id' => $professor->id,
            'data' => '2026-03-03',
            'hora_inicio' => '08:00:00',
            'hora_fim' => '09:00:00',
            'conteudo_ministrado' => 'Equações',
        ]);

        $apuracao = $service->apurarTurma($turma);

        $this->assertEquals(2, $apuracao['dias_previstos']);
        $this->assertEquals(2.0, $apuracao['horas_previstas']);
        $this->assertEquals(200, $apuracao['minimo_legal_dias']);
        $this->assertEquals(800, $apuracao['minimo_legal_horas']);
        $this->assertEquals('insuficiente', $apuracao['status']); // Com apenas 2 dias e 2h de 200d/800h, está insuficiente
    }

    public function test_agendamento_e_conclusao_de_reposicao_de_aula(): void
    {
        $user = $this->autenticarComoAdmin();
        $service = app(CargaLetivaService::class);
        [$turma, $disciplina, $professor] = $this->criarCenarioTurma('Fundamental');

        $reposicao = $service->agendarReposicao([
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'pessoa_id' => $professor->id,
            'data_reposicao' => '2026-04-18', // Sábado letivo de reposição
            'hora_inicio' => '08:00:00',
            'hora_fim' => '10:00:00', // 2 horas (120 min)
            'motivo' => 'Compensação de feriado municipal',
        ], $user);

        $this->assertNotNull($reposicao->id);
        $this->assertEquals('agendada', $reposicao->status);
        $this->assertEquals(120, $reposicao->duracaoMinutos());
        $this->assertNotNull($reposicao->cronograma_aula_reposicao_id);

        // Verifica se a aula foi criada no cronograma para a turma
        $aulaCriada = CronogramaAula::find($reposicao->cronograma_aula_reposicao_id);
        $this->assertNotNull($aulaCriada);
        $this->assertEquals('2026-04-18', $aulaCriada->data->toDateString());

        // Conclusão da reposição
        $service->concluirReposicao($reposicao);
        $this->assertEquals('realizada', $reposicao->fresh()->status);
    }

    public function test_listagem_reposicao_aulas_no_filament_com_ajuda(): void
    {
        $this->autenticarComoAdmin();
        [$turma, $disciplina, $professor] = $this->criarCenarioTurma('Fundamental');

        $reposicao = ReposicaoAula::create([
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'pessoa_id' => $professor->id,
            'data_reposicao' => '2026-05-10',
            'hora_inicio' => '08:00:00',
            'hora_fim' => '08:50:00',
            'motivo' => 'Reposição de aula teste',
            'status' => 'agendada',
        ]);

        Livewire::test(ListReposicaoAulas::class)
            ->assertCanSeeTableRecords([$reposicao])
            ->assertActionExists('ajuda');
    }
}
