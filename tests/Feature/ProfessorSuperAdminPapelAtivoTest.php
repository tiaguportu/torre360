<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\LancamentoNotasGrade;
use App\Filament\Resources\Avaliacaos\AvaliacaoResource;
use App\Filament\Resources\Disciplinas\DisciplinaResource;
use App\Filament\Resources\Pessoas\PessoaResource;
use App\Filament\Resources\Turmas\TurmaResource;
use App\Filament\Widgets\FrequenciaPendenteWidget;
use App\Models\CronogramaAula;
use App\Models\Disciplina;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Concerns\CriaAvaliacaoTestTrait;
use Tests\TestCase;

/**
 * Quem acumula professor e super_admin (ex.: usuário 68) só fica restrito ao que leciona enquanto atua como professor.
 * Ao trocar para super_admin em "Atuar como", vê e lança o que é de outros professores. `hasRole('professor')` ignora o
 * papel ativo, então o escopo precisa vir de `User::atuaComoProfessor()`.
 */
class ProfessorSuperAdminPapelAtivoTest extends TestCase
{
    use CriaAvaliacaoTestTrait;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['super_admin', 'professor', 'secretaria'] as $papel) {
            Role::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);
        }

        // As permissões resolvem pelo papel ativo (User::hasPermissionTo): o professor precisa tê-las no próprio papel.
        Role::findByName('professor', 'web')->givePermissionTo(collect([
            'ViewAny:Avaliacao', 'View:Avaliacao', 'LancarNotas:Avaliacao', 'GerenciarGabarito:Avaliacao',
            'View:LancamentoNotasGrade', 'View:FrequenciaPendenteWidget',
        ])->map(fn (string $nome) => Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']))->all());
    }

    /**
     * @param  list<string>  $papeis
     */
    private function usuario(array $papeis, ?string $papelAtivo, ?Pessoa $pessoa = null): User
    {
        $user = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $user->assignRole($papeis);

        if ($pessoa) {
            $user->pessoas()->attach($pessoa->id);
        }

        if ($papelAtivo) {
            session(['active_role' => $papelAtivo]);
        }

        $this->actingAs($user);

        return $user;
    }

    public function test_papel_ativo_decide_se_o_usuario_atua_como_professor(): void
    {
        $professorPuro = $this->usuario(['professor'], null);
        $this->assertTrue($professorPuro->atuaComoProfessor(), 'professor sem papel ativo na sessão continua restrito');

        $duplo = $this->usuario(['professor', 'super_admin'], 'professor');
        $this->assertTrue($duplo->atuaComoProfessor());

        session(['active_role' => 'super_admin']);
        $this->assertFalse($duplo->atuaComoProfessor(), 'atuando como super_admin não vale o escopo de professor');

        session()->forget('active_role');
        $this->assertFalse($duplo->atuaComoProfessor(), 'sem papel ativo, o papel de gestão prevalece (igual a hasPermissionTo)');

        session(['active_role' => 'secretaria']);
        $this->assertFalse($duplo->atuaComoProfessor(), 'papel ativo que o usuário não possui não conta');
        $this->assertTrue($professorPuro->atuaComoProfessor(), 'papel ativo inválido não solta um professor puro');

        $semProfessor = $this->usuario(['super_admin'], 'super_admin');
        $this->assertFalse($semProfessor->atuaComoProfessor());
    }

    public function test_avaliacoes_so_do_professor_quando_atua_como_professor_e_todas_como_super_admin(): void
    {
        $pessoa = Pessoa::factory()->create();
        $duplo = $this->usuario(['professor', 'super_admin'], 'professor', $pessoa);

        $propria = $this->criarAvaliacao(['professor_id' => $pessoa->id]);
        $alheia = $this->criarAvaliacao();

        $this->assertEqualsCanonicalizing(
            [$propria->id],
            AvaliacaoResource::getEloquentQuery()->pluck('id')->all(),
        );

        session(['active_role' => 'super_admin']);

        $this->assertEqualsCanonicalizing(
            [$propria->id, $alheia->id],
            AvaliacaoResource::getEloquentQuery()->pluck('id')->all(),
        );
        $this->assertTrue($duplo->can('gerenciarGabarito', $alheia));

        session(['active_role' => 'professor']);
        $this->assertFalse($duplo->can('gerenciarGabarito', $alheia));
        $this->assertTrue($duplo->can('gerenciarGabarito', $propria));
    }

    public function test_turmas_disciplinas_e_pessoas_seguem_o_papel_ativo(): void
    {
        $pessoa = Pessoa::factory()->create();
        $this->usuario(['professor', 'super_admin'], 'professor', $pessoa);

        $turmaPropria = Turma::factory()->create();
        $turmaAlheia = Turma::factory()->create();
        $disciplinaPropria = Disciplina::factory()->create();
        $disciplinaAlheia = Disciplina::factory()->create();

        CronogramaAula::factory()->create([
            'turma_id' => $turmaPropria->id,
            'disciplina_id' => $disciplinaPropria->id,
            'pessoa_id' => $pessoa->id,
        ]);
        CronogramaAula::factory()->create([
            'turma_id' => $turmaAlheia->id,
            'disciplina_id' => $disciplinaAlheia->id,
            'pessoa_id' => Pessoa::factory()->create()->id,
        ]);

        $this->assertEqualsCanonicalizing([$turmaPropria->id], TurmaResource::getEloquentQuery()->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$disciplinaPropria->id], DisciplinaResource::getEloquentQuery()->pluck('id')->all());
        $estranha = Pessoa::factory()->create();
        $this->assertFalse(PessoaResource::getEloquentQuery()->whereKey($estranha->id)->exists());

        session(['active_role' => 'super_admin']);

        $this->assertEqualsCanonicalizing(
            [$turmaPropria->id, $turmaAlheia->id],
            TurmaResource::getEloquentQuery()->pluck('id')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$disciplinaPropria->id, $disciplinaAlheia->id],
            DisciplinaResource::getEloquentQuery()->pluck('id')->all(),
        );
        $this->assertTrue(PessoaResource::getEloquentQuery()->whereKey($estranha->id)->exists());
    }

    public function test_pendencias_de_frequencia_seguem_o_papel_ativo(): void
    {
        $pessoa = Pessoa::factory()->create();
        $outraPessoa = Pessoa::factory()->create();
        $this->usuario(['professor', 'super_admin'], 'professor', $pessoa);

        $turma = Turma::factory()->create();
        Matricula::factory()->create(['turma_id' => $turma->id, 'pessoa_id' => Pessoa::factory()->create()->id]);
        $disciplina = Disciplina::factory()->create();
        $hoje = now()->toDateString();

        $minha = CronogramaAula::factory()->create([
            'turma_id' => $turma->id, 'disciplina_id' => $disciplina->id, 'pessoa_id' => $pessoa->id, 'data' => $hoje,
        ]);
        $deOutro = CronogramaAula::factory()->create([
            'turma_id' => $turma->id, 'disciplina_id' => $disciplina->id, 'pessoa_id' => $outraPessoa->id, 'data' => $hoje,
        ]);

        $comoProfessor = Livewire::test(FrequenciaPendenteWidget::class)->instance()->getPendenciasAgrupadas();
        $this->assertEqualsCanonicalizing([$minha->id], $comoProfessor->flatten()->pluck('id')->all());

        session(['active_role' => 'super_admin']);

        $comoSuperAdmin = Livewire::test(FrequenciaPendenteWidget::class)->instance()->getPendenciasAgrupadas();
        $this->assertEqualsCanonicalizing([$minha->id, $deOutro->id], $comoSuperAdmin->flatten()->pluck('id')->all());
    }

    public function test_lancamento_de_notas_em_grade_libera_avaliacao_de_outro_professor_so_como_super_admin(): void
    {
        $pessoa = Pessoa::factory()->create();
        $this->usuario(['professor', 'super_admin'], 'professor', $pessoa);

        $alheia = $this->criarAvaliacao();

        $comoProfessor = Livewire::test(LancamentoNotasGrade::class)
            ->set('data.avaliacao_id', $alheia->id)
            ->call('carregarGrade');
        $this->assertNull($comoProfessor->instance()->avaliacaoAtual);

        session(['active_role' => 'super_admin']);

        $comoSuperAdmin = Livewire::test(LancamentoNotasGrade::class)
            ->set('data.avaliacao_id', $alheia->id)
            ->call('carregarGrade');
        $this->assertSame($alheia->id, $comoSuperAdmin->instance()->avaliacaoAtual?->id);
    }
}
