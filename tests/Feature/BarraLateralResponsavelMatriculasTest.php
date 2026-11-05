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

/**
 * Barra lateral do responsável (EnsureActiveRole): lista as matrículas ativas, pendentes e em reserva
 * de cada aluno; com mais de uma do mesmo aluno, os atalhos ficam sob o aluno e levam o período letivo.
 */
class BarraLateralResponsavelMatriculasTest extends TestCase
{
    use RefreshDatabase;

    private User $responsavel;

    protected function setUp(): void
    {
        parent::setUp();

        // O teste renderiza a página inteira do painel; sem `public/build` o @vite derrubaria a resposta.
        $this->withoutVite();

        Role::firstOrCreate(['name' => 'responsavel', 'guard_name' => 'web']);

        $pessoa = Pessoa::factory()->create(['nome' => 'Responsavel Barra Teste']);
        $this->responsavel = User::factory()->create(['activated_at' => now()]);
        $pessoa->users()->save($this->responsavel);
        $this->responsavel->assignRole('responsavel');
    }

    private function aluno(string $nome): Pessoa
    {
        $aluno = Pessoa::factory()->create(['nome' => $nome]);
        $this->responsavel->pessoa->alunos()->attach($aluno->id);

        return $aluno;
    }

    private function matricula(Pessoa $aluno, string $situacao, string $periodo, ?string $turma = null): Matricula
    {
        $periodoLetivo = PeriodoLetivo::firstOrCreate(
            ['nome' => $periodo],
            ['data_inicio' => "{$periodo}-01-01", 'data_fim' => "{$periodo}-12-31"],
        );

        return Matricula::factory()->create([
            'pessoa_id' => $aluno->id,
            'situacao' => $situacao,
            'turma_id' => Turma::factory()->create(array_filter([
                'periodo_letivo_id' => $periodoLetivo->id,
                'nome' => $turma,
            ]))->id,
        ]);
    }

    private function barraLateral(): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->responsavel)
            ->withSession(['active_role' => 'responsavel'])
            ->get('/admin');
    }

    public function test_mostra_matriculas_ativa_pendente_e_reserva(): void
    {
        $maria = $this->aluno('Maria Ativa');
        $joao = $this->aluno('Joao Pendente');
        $ana = $this->aluno('Ana Reserva');
        $this->matricula($maria, 'ativa', '2026');
        $this->matricula($joao, 'pendente', '2026');
        $this->matricula($ana, 'reserva', '2026');

        $this->barraLateral()
            ->assertOk()
            ->assertSee('Maria Ativa')
            ->assertSee('Joao Pendente')
            ->assertSee('Ana Reserva');
    }

    public function test_nao_mostra_aluno_so_com_matricula_cancelada_trancada_concluida_ou_evasao(): void
    {
        foreach (['cancelada', 'trancada', 'concluido', 'evasao'] as $situacao) {
            $this->matricula($this->aluno("Aluno {$situacao}"), $situacao, '2026');
        }

        $this->barraLateral()
            ->assertOk()
            ->assertDontSee('Aluno cancelada')
            ->assertDontSee('Aluno trancada')
            ->assertDontSee('Aluno concluido')
            ->assertDontSee('Aluno evasao');
    }

    public function test_aluno_com_uma_matricula_mantem_os_rotulos_sem_periodo(): void
    {
        $this->matricula($this->aluno('Aluno Unico'), 'ativa', '2026');

        $this->barraLateral()
            ->assertOk()
            ->assertSee('Dados Cadastrais')
            ->assertSee('Preceptorias')
            ->assertSee('Documentos')
            ->assertDontSee('Documentos · 2026');
    }

    public function test_aluno_com_varias_matriculas_diferencia_cada_uma_pelo_periodo_letivo(): void
    {
        $aluno = $this->aluno('Aluno Duas Matriculas');
        $this->matricula($aluno, 'ativa', '2026');
        $this->matricula($aluno, 'reserva', '2027');
        $this->matricula($aluno, 'pendente', '2028');
        // Matrícula encerrada do mesmo aluno não entra na lista.
        $this->matricula($aluno, 'cancelada', '2025');

        $resposta = $this->barraLateral()->assertOk();

        $resposta->assertSeeInOrder([
            'Aluno Duas Matriculas',
            'Dados Cadastrais',
            'Preceptorias · 2026',
            'Documentos · 2026',
            'Preceptorias · 2028 (Pendente)',
            'Documentos · 2028 (Pendente)',
            'Preceptorias · 2027 (Reserva)',
            'Documentos · 2027 (Reserva)',
        ]);
        $resposta->assertDontSee('· 2025');
        // O aluno aparece como um único grupo, com um só "Dados Cadastrais".
        $this->assertSame(1, substr_count($resposta->getContent(), 'Dados Cadastrais'));
    }

    public function test_matriculas_do_mesmo_periodo_e_situacao_se_distinguem_pela_turma(): void
    {
        $aluno = $this->aluno('Aluno Dois Cursos');
        $this->matricula($aluno, 'ativa', '2026', 'Turma Regular');
        $this->matricula($aluno, 'ativa', '2026', 'Turma Extra');

        $this->barraLateral()
            ->assertOk()
            ->assertSee('Documentos · 2026 – Turma Regular')
            ->assertSee('Documentos · 2026 – Turma Extra');
    }

    public function test_cada_aluno_tem_seu_proprio_grupo(): void
    {
        $maria = $this->aluno('Maria Grupo');
        $joao = $this->aluno('Joao Grupo');
        $this->matricula($maria, 'ativa', '2026');
        $this->matricula($maria, 'ativa', '2027');
        $this->matricula($joao, 'ativa', '2026');

        $resposta = $this->barraLateral()->assertOk();

        $resposta->assertSee('Documentos · 2027');
        // João tem uma só matrícula: o rótulo do grupo dele não leva período.
        $resposta->assertSeeInOrder(['Joao Grupo', 'Dados Cadastrais', 'Preceptorias', 'Documentos']);
        $this->assertSame(1, substr_count($resposta->getContent(), 'Documentos · 2026'));
    }
}
