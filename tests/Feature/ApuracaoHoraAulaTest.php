<?php

namespace Tests\Feature;

use App\Filament\Resources\ApuracaoHoraAulas\Pages\ListApuracaoHoraAulas;
use App\Models\ApuracaoHoraAula;
use App\Models\CronogramaAula;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\FrequenciaEscolar;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\ReposicaoAula;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\Turno;
use App\Models\Unidade;
use App\Models\User;
use App\Services\ApuracaoHoraAulaService;
use Database\Factories\PeriodoLetivoFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApuracaoHoraAulaTest extends TestCase
{
    use RefreshDatabase;

    private function autenticarComoAdmin(): User
    {
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $perms = [
            'ViewAny:ApuracaoHoraAula',
            'View:ApuracaoHoraAula',
            'Create:ApuracaoHoraAula',
            'Update:ApuracaoHoraAula',
            'Delete:ApuracaoHoraAula',
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

    private function criarCenarioDocente(): array
    {
        $unidade = Unidade::create(['nome' => 'Unidade Teste', 'situacao_funcionamento' => '1']);
        $curso = Curso::create(['unidade_id' => $unidade->id, 'nome_externo' => 'Ensino Fundamental', 'nome_interno' => 'EF']);
        $serie = Serie::create(['curso_id' => $curso->id, 'nome' => '9º Ano', 'sistema_avaliacao' => 'Nota']);
        $turno = Turno::create(['nome' => 'Manhã', 'hora_inicio' => '07:30:00', 'hora_fim' => '12:00:00']);
        $periodo = PeriodoLetivo::create(['nome' => '2026', 'ano' => 2026, 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);

        $turma = Turma::create([
            'periodo_letivo_id' => PeriodoLetivoFactory::idPadrao(),
            'nome' => 'Turma 901',
            'serie_id' => $serie->id,
            'turno_id' => $turno->id,
            'vagas_maximas' => 30,
        ]);

        $aluno = Pessoa::create(['nome' => 'Aluno Teste', 'data_nascimento' => '2011-03-10']);
        $matricula = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'situacao' => 'ativa',
        ]);

        $disciplina = Disciplina::create(['nome' => 'História', 'sigla' => 'HIST']);
        $professor = Pessoa::create(['nome' => 'Professor Carlos', 'data_nascimento' => '1982-06-25', 'cpf' => '98765432100']);

        return [$professor, $turma, $disciplina, $matricula];
    }

    public function test_apuracao_mensal_calcula_aulas_reposicoes_e_dsr(): void
    {
        $service = app(ApuracaoHoraAulaService::class);
        [$professor, $turma, $disciplina, $matricula] = $this->criarCenarioDocente();

        // 1. Cria 2 aulas em abril/2026 para o professor
        $aula1 = CronogramaAula::create([
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'pessoa_id' => $professor->id,
            'data' => '2026-04-06',
            'hora_inicio' => '08:00:00',
            'hora_fim' => '08:50:00',
            'conteudo_ministrado' => 'Revolução Francesa',
        ]);

        $aula2 = CronogramaAula::create([
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'pessoa_id' => $professor->id,
            'data' => '2026-04-13',
            'hora_inicio' => '08:00:00',
            'hora_fim' => '08:50:00',
            // Sem conteúdo nem frequência (aula não ministrada / falta)
        ]);

        // Lança frequência na aula 1 (aula dada)
        FrequenciaEscolar::create([
            'matricula_id' => $matricula->id,
            'cronograma_aula_id' => $aula1->id,
            'situacao' => 'presente',
        ]);

        // 2. Cria 1 reposição realizada em abril/2026 (Onda 49)
        ReposicaoAula::create([
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'pessoa_id' => $professor->id,
            'data_reposicao' => '2026-04-18',
            'hora_inicio' => '08:00:00',
            'hora_fim' => '08:50:00',
            'motivo' => 'Reposição feriado',
            'status' => 'realizada',
        ]);

        // 3. Processa apuração (valor R$ 50,00 por aula)
        $apuracao = $service->apurarProfessor($professor, 2026, 4, 50.00, 2.0);

        $this->assertEquals(2, $apuracao->total_aulas_previstas);
        $this->assertEquals(1, $apuracao->total_aulas_ministradas);
        $this->assertEquals(1, $apuracao->total_aulas_reposicao);
        $this->assertEquals(1, $apuracao->total_aulas_faltadas);
        $this->assertEquals(2.0, (float) $apuracao->total_horas_atividade);

        // Total remunerável = 1 ministrada + 1 reposição + 2 atividade = 4 aulas
        // Base = 4 * 50.00 = R$ 200,00
        // DSR = 200 * 16.67% = R$ 33,34
        // Bruto = R$ 233,34
        $this->assertEquals(233.34, (float) $apuracao->valor_total_bruto);
        $this->assertEquals('aberto', $apuracao->status);

        // 4. Homologa apuração
        $service->homologarApuracao($apuracao);
        $this->assertEquals('homologado', $apuracao->fresh()->status);
        $this->assertNotNull($apuracao->fresh()->conferido_em);

        // 5. Exporta CSV
        $csv = $service->exportarCsv(2026, 4);
        $this->assertStringContainsString('Competencia;CPF;Professor', $csv);
        $this->assertStringContainsString('Professor Carlos', $csv);
        $this->assertStringContainsString('04/2026', $csv);
        $this->assertStringContainsString('233,34', $csv);
    }

    public function test_listagem_apuracao_hora_aulas_no_filament_com_ajuda(): void
    {
        $this->autenticarComoAdmin();
        [$professor] = $this->criarCenarioDocente();

        $apuracao = ApuracaoHoraAula::create([
            'pessoa_id' => $professor->id,
            'ano' => 2026,
            'mes' => 5,
            'total_aulas_previstas' => 20,
            'total_aulas_ministradas' => 20,
            'valor_hora_aula' => 50.00,
            'valor_total_bruto' => 1166.70,
            'status' => 'aberto',
        ]);

        Livewire::test(ListApuracaoHoraAulas::class)
            ->assertCanSeeTableRecords([$apuracao])
            ->assertActionExists('ajuda');
    }
}
