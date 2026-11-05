<?php

namespace Tests\Feature\Policies;

use App\Enums\StatusParecerDescritivo;
use App\Models\Avaliacao;
use App\Models\Disciplina;
use App\Models\Funcionario;
use App\Models\Matricula;
use App\Models\ParecerDescritivo;
use App\Models\Pessoa;
use App\Models\PortfolioItem;
use App\Models\Turma;
use App\Models\User;
use App\Policies\AvaliacaoPolicy;
use App\Policies\ParecerDescritivoPolicy;
use App\Policies\PortfolioItemPolicy;
use App\Support\EscopoProfessor;
use App\Support\FuncionarioDoUsuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Professor só enxerga o que leciona (turma em que é conselheiro ou tem disciplina); coordenação e administração não são
 * recortadas. É o recorte que protege pareceres, portfólio e gabaritos de outros professores.
 */
class EscopoDoProfessorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<string>  $permissoes
     * @return array{0: User, 1: Pessoa}
     */
    private function usuario(string $papel, array $permissoes = []): array
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        foreach ($permissoes as $permissao) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permissao, 'guard_name' => 'web']));
        }

        $user = User::factory()->create(['activated_at' => now()->subDay()]);
        $user->assignRole($role);
        $pessoa = Pessoa::factory()->create();
        $user->pessoas()->attach($pessoa->id);
        session(['active_role' => $papel]);

        return [$user->fresh(), $pessoa];
    }

    private function matriculaNaTurma(?int $conselheiroId = null, ?int $professorDaDisciplinaId = null): Matricula
    {
        $turma = Turma::factory()->create(['professor_conselheiro_id' => $conselheiroId]);

        if ($professorDaDisciplinaId) {
            $turma->disciplinas()->attach(Disciplina::factory()->create()->id, ['professor_id' => $professorDaDisciplinaId]);
        }

        return Matricula::factory()->create(['turma_id' => $turma->id]);
    }

    public function test_professor_conselheiro_ve_os_alunos_da_turma(): void
    {
        [$professor, $pessoa] = $this->usuario('professor');

        $this->assertTrue(EscopoProfessor::podeVerMatricula($professor, $this->matriculaNaTurma(conselheiroId: $pessoa->id)));
    }

    public function test_professor_de_uma_disciplina_ve_os_alunos_da_turma(): void
    {
        [$professor, $pessoa] = $this->usuario('professor');

        $this->assertTrue(EscopoProfessor::podeVerMatricula($professor, $this->matriculaNaTurma(professorDaDisciplinaId: $pessoa->id)));
    }

    public function test_professor_nao_ve_alunos_de_turma_que_nao_leciona(): void
    {
        [$professor] = $this->usuario('professor');
        $outro = Pessoa::factory()->create();

        $this->assertFalse(EscopoProfessor::podeVerMatricula($professor, $this->matriculaNaTurma($outro->id, $outro->id)));
    }

    public function test_coordenacao_nao_e_recortada(): void
    {
        [$coordenador] = $this->usuario('coordenador');
        $outro = Pessoa::factory()->create();

        $this->assertTrue(EscopoProfessor::podeVerMatricula($coordenador, $this->matriculaNaTurma($outro->id)));
    }

    public function test_sem_usuario_ou_sem_matricula_nao_ve_nada(): void
    {
        [$professor] = $this->usuario('professor');

        $this->assertFalse(EscopoProfessor::podeVerMatricula(null, $this->matriculaNaTurma()));
        $this->assertFalse(EscopoProfessor::podeVerMatricula($professor, null));
        $this->assertSame(0, EscopoProfessor::restringirMatriculas(Matricula::query(), null)->count());
    }

    public function test_restringir_matriculas_devolve_so_as_do_professor(): void
    {
        [$professor, $pessoa] = $this->usuario('professor');
        $minhas = [
            $this->matriculaNaTurma(conselheiroId: $pessoa->id)->id,
            $this->matriculaNaTurma(professorDaDisciplinaId: $pessoa->id)->id,
        ];
        $this->matriculaNaTurma(Pessoa::factory()->create()->id);

        $ids = EscopoProfessor::restringirMatriculas(Matricula::query(), $professor)->pluck('id')->all();

        $this->assertEqualsCanonicalizing($minhas, $ids);
    }

    public function test_pessoa_ids_junta_as_pessoas_vinculadas_sem_repetir(): void
    {
        [$professor, $pessoa] = $this->usuario('professor');
        $segunda = Pessoa::factory()->create();
        $professor->pessoas()->attach($segunda->id);

        $this->assertEqualsCanonicalizing([$pessoa->id, $segunda->id], EscopoProfessor::pessoaIds($professor->fresh()));
    }

    // ---------- Funcionário do usuário ----------

    public function test_funcionario_do_usuario_e_o_cadastro_ativo_da_pessoa_dele(): void
    {
        [$user, $pessoa] = $this->usuario('professor');
        $funcionario = Funcionario::factory()->create(['pessoa_id' => $pessoa->id]);
        Funcionario::factory()->create(); // de outra pessoa

        $this->assertTrue(FuncionarioDoUsuario::para($user)->is($funcionario));
        $this->assertNull(FuncionarioDoUsuario::para(null));
    }

    public function test_funcionario_desligado_nao_e_o_do_usuario_mas_ainda_e_o_proprio(): void
    {
        [$user, $pessoa] = $this->usuario('professor');
        $desligado = Funcionario::factory()->create(['pessoa_id' => $pessoa->id, 'data_desligamento' => now()->subMonth()->toDateString()]);

        $this->assertNull(FuncionarioDoUsuario::para($user), 'quem saiu não bate ponto');
        $this->assertTrue(FuncionarioDoUsuario::ehOProprio($user, $desligado), 'mas ainda consulta o próprio histórico');
        $this->assertFalse(FuncionarioDoUsuario::ehOProprio($user, Funcionario::factory()->create()));
        $this->assertFalse(FuncionarioDoUsuario::ehOProprio(null, $desligado));
    }

    public function test_funcionario_com_dois_cadastros_ativos_fica_o_mais_recente(): void
    {
        [$user, $pessoa] = $this->usuario('professor');
        Funcionario::factory()->create(['pessoa_id' => $pessoa->id, 'data_admissao' => '2020-01-01']);
        $recente = Funcionario::factory()->create(['pessoa_id' => $pessoa->id, 'data_admissao' => '2024-03-01']);

        $this->assertTrue(FuncionarioDoUsuario::para($user)->is($recente));
    }

    // ---------- Policies que dependem do escopo ----------

    public function test_parecer_professor_ve_e_edita_so_o_de_aluno_da_turma_dele(): void
    {
        [$professor, $pessoa] = $this->usuario('professor', ['View:ParecerDescritivo', 'Update:ParecerDescritivo', 'GerarComIa:ParecerDescritivo']);
        $policy = new ParecerDescritivoPolicy;
        $meu = (new ParecerDescritivo)->forceFill(['status' => StatusParecerDescritivo::Rascunho])->setRelation('matricula', $this->matriculaNaTurma(conselheiroId: $pessoa->id));
        $alheio = (new ParecerDescritivo)->forceFill(['status' => StatusParecerDescritivo::Rascunho])->setRelation('matricula', $this->matriculaNaTurma(Pessoa::factory()->create()->id));

        $this->assertTrue($policy->view($professor, $meu));
        $this->assertTrue($policy->update($professor, $meu));
        $this->assertTrue($policy->gerarComIa($professor, $meu));
        $this->assertTrue($policy->enviarParaRevisao($professor, $meu));

        $this->assertFalse($policy->view($professor, $alheio));
        $this->assertFalse($policy->update($professor, $alheio));
        $this->assertFalse($policy->gerarComIa($professor, $alheio));
        $this->assertFalse($policy->enviarParaRevisao($professor, $alheio));
    }

    public function test_parecer_publicado_nao_se_edita_nem_se_gera_com_ia(): void
    {
        [$professor, $pessoa] = $this->usuario('professor', ['View:ParecerDescritivo', 'Update:ParecerDescritivo', 'GerarComIa:ParecerDescritivo']);
        $policy = new ParecerDescritivoPolicy;
        $publicado = (new ParecerDescritivo)->forceFill(['status' => StatusParecerDescritivo::Publicado])->setRelation('matricula', $this->matriculaNaTurma(conselheiroId: $pessoa->id));
        $emRevisao = (new ParecerDescritivo)->forceFill(['status' => StatusParecerDescritivo::EmRevisao])->setRelation('matricula', $this->matriculaNaTurma(conselheiroId: $pessoa->id));

        $this->assertTrue($policy->view($professor, $publicado));
        $this->assertFalse($policy->update($professor, $publicado));
        $this->assertFalse($policy->gerarComIa($professor, $publicado));
        $this->assertFalse($policy->enviarParaRevisao($professor, $emRevisao), 'só o rascunho vai para revisão');
    }

    public function test_parecer_coordenacao_ve_o_de_qualquer_turma(): void
    {
        [$coordenador] = $this->usuario('coordenador', ['View:ParecerDescritivo']);
        $parecer = (new ParecerDescritivo)->forceFill(['status' => StatusParecerDescritivo::Rascunho])->setRelation('matricula', $this->matriculaNaTurma(Pessoa::factory()->create()->id));

        $this->assertTrue((new ParecerDescritivoPolicy)->view($coordenador, $parecer));
    }

    public function test_portfolio_segue_o_mesmo_escopo_do_professor(): void
    {
        [$professor, $pessoa] = $this->usuario('professor', ['View:PortfolioItem', 'Update:PortfolioItem', 'Delete:PortfolioItem']);
        $policy = new PortfolioItemPolicy;
        $meu = (new PortfolioItem)->setRelation('matricula', $this->matriculaNaTurma(conselheiroId: $pessoa->id));
        $alheio = (new PortfolioItem)->setRelation('matricula', $this->matriculaNaTurma(Pessoa::factory()->create()->id));

        foreach (['view', 'update', 'delete'] as $metodo) {
            $this->assertTrue($policy->{$metodo}($professor, $meu), "{$metodo}: item de aluno da turma dele");
            $this->assertFalse($policy->{$metodo}($professor, $alheio), "{$metodo}: item de aluno de outra turma");
        }
    }

    public function test_gabarito_professor_so_gerencia_a_avaliacao_dele(): void
    {
        [$professor, $pessoa] = $this->usuario('professor', ['GerenciarGabarito:Avaliacao', 'CorrigirGabarito:Avaliacao']);
        $policy = new AvaliacaoPolicy;
        $minha = (new Avaliacao)->forceFill(['professor_id' => $pessoa->id]);
        $dele = (new Avaliacao)->forceFill(['professor_id' => Pessoa::factory()->create()->id]);
        $semProfessor = (new Avaliacao)->forceFill(['professor_id' => null]);

        $this->assertTrue($policy->gerenciarGabarito($professor, $minha));
        $this->assertTrue($policy->corrigirGabarito($professor, $minha));
        $this->assertFalse($policy->gerenciarGabarito($professor, $dele));
        $this->assertFalse($policy->corrigirGabarito($professor, $dele));
        $this->assertFalse($policy->gerenciarGabarito($professor, $semProfessor));
    }

    public function test_gabarito_coordenacao_gerencia_de_qualquer_professor(): void
    {
        [$coordenador] = $this->usuario('coordenador', ['GerenciarGabarito:Avaliacao']);
        $dele = (new Avaliacao)->forceFill(['professor_id' => Pessoa::factory()->create()->id]);

        $this->assertTrue((new AvaliacaoPolicy)->gerenciarGabarito($coordenador, $dele));
    }

    public function test_homologar_vale_para_quem_homologa_ou_edita_avaliacoes(): void
    {
        $policy = new AvaliacaoPolicy;

        // só vale a permissão do papel ativo da sessão: cada usuário é avaliado logo depois de criado
        [$homologador] = $this->usuario('coordenador', ['Homologar:Avaliacao']);
        $this->assertTrue($policy->homologar($homologador, new Avaliacao));

        [$editor] = $this->usuario('secretaria', ['Update:Avaliacao']);
        $this->assertTrue($policy->homologar($editor, new Avaliacao));

        [$ninguem] = $this->usuario('aluno', []);
        $this->assertFalse($policy->homologar($ninguem, new Avaliacao));
    }
}
