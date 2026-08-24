<?php

namespace Tests\Feature;

use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardLoadTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_nao_autenticado_e_redirecionado_para_login_do_portal(): void
    {
        $response = $this->get('/portal');

        $response->assertRedirect(url('/portal/login'));
    }

    public function test_dashboard_exibe_apenas_alunos_com_matricula(): void
    {
        Role::firstOrCreate(['name' => 'responsavel', 'guard_name' => 'web']);

        $responsavelPessoa = Pessoa::factory()->create(['nome' => 'Responsavel Dashboard Teste']);
        $user = User::factory()->create(['activated_at' => now()]);
        $responsavelPessoa->users()->save($user);
        $user->assignRole('responsavel');

        $dependenteComMatricula = Pessoa::factory()->create(['nome' => 'Dependente Matriculado']);
        $dependenteSemMatricula = Pessoa::factory()->create(['nome' => 'Dependente Sem Matricula']);
        $responsavelPessoa->alunos()->attach([$dependenteComMatricula->id, $dependenteSemMatricula->id]);

        $periodo = PeriodoLetivo::factory()->create(['nome' => '2026']);
        $turma = Turma::factory()->create(['nome' => 'Turma A', 'periodo_letivo_id' => $periodo->id]);

        Matricula::factory()->create([
            'pessoa_id' => $dependenteComMatricula->id,
            'turma_id' => $turma->id,
            'periodo_letivo_id' => $periodo->id,
            'situacao' => 'ativa',
        ]);

        $response = $this->actingAs($user)->get('/portal');

        $response->assertOk()
            ->assertSee('Dependente Matriculado')
            ->assertSee('Turma A')
            ->assertSee('2026')
            ->assertDontSee('Dependente Sem Matricula');
    }

    public function test_dashboard_exibe_mensagem_quando_nenhum_aluno_vinculado(): void
    {
        Role::firstOrCreate(['name' => 'responsavel', 'guard_name' => 'web']);

        $responsavelPessoa = Pessoa::factory()->create(['nome' => 'Responsavel Sem Dependente']);
        $user = User::factory()->create(['activated_at' => now()]);
        $responsavelPessoa->users()->save($user);
        $user->assignRole('responsavel');

        $response = $this->actingAs($user)->get('/portal');

        $response->assertOk()
            ->assertSee('Nenhum aluno vinculado ao seu cadastro foi encontrado.');
    }

    public function test_dashboard_lista_todos_os_dependentes_matriculados_de_um_responsavel(): void
    {
        Role::firstOrCreate(['name' => 'responsavel', 'guard_name' => 'web']);

        $responsavelPessoa = Pessoa::factory()->create(['nome' => 'Responsavel Multi Dependente']);
        $user = User::factory()->create(['activated_at' => now()]);
        $responsavelPessoa->users()->save($user);
        $user->assignRole('responsavel');

        $filho1 = Pessoa::factory()->create(['nome' => 'Filho Um']);
        $filho2 = Pessoa::factory()->create(['nome' => 'Filho Dois']);
        $responsavelPessoa->alunos()->attach([$filho1->id, $filho2->id]);

        Matricula::factory()->create(['pessoa_id' => $filho1->id, 'situacao' => 'ativa']);
        Matricula::factory()->create(['pessoa_id' => $filho2->id, 'situacao' => 'ativa']);

        $response = $this->actingAs($user)->get('/portal');

        $response->assertOk()
            ->assertSee('Filho Um')
            ->assertSee('Filho Dois');
    }

    public function test_dashboard_nao_exibe_alunos_de_outro_responsavel(): void
    {
        Role::firstOrCreate(['name' => 'responsavel', 'guard_name' => 'web']);

        $responsavelPessoa = Pessoa::factory()->create(['nome' => 'Responsavel A']);
        $user = User::factory()->create(['activated_at' => now()]);
        $responsavelPessoa->users()->save($user);
        $user->assignRole('responsavel');

        $meuDependente = Pessoa::factory()->create(['nome' => 'Meu Dependente']);
        $responsavelPessoa->alunos()->attach($meuDependente->id);
        Matricula::factory()->create(['pessoa_id' => $meuDependente->id, 'situacao' => 'ativa']);

        $outroResponsavel = Pessoa::factory()->create(['nome' => 'Responsavel B']);
        $alunoAlheio = Pessoa::factory()->create(['nome' => 'Aluno Alheio']);
        $outroResponsavel->alunos()->attach($alunoAlheio->id);
        Matricula::factory()->create(['pessoa_id' => $alunoAlheio->id, 'situacao' => 'ativa']);

        $response = $this->actingAs($user)->get('/portal');

        $response->assertOk()
            ->assertSee('Meu Dependente')
            ->assertDontSee('Aluno Alheio');
    }
}
