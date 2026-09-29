<?php

namespace Tests\Feature;

use App\Filament\Resources\PlanoAulas\Pages\EditPlanoAula;
use App\Filament\Resources\PlanoAulas\Pages\ListPlanoAulas;
use App\Models\CronogramaAula;
use App\Models\Disciplina;
use App\Models\Habilidade;
use App\Models\Pessoa;
use App\Models\PlanoAula;
use App\Models\Turma;
use App\Models\User;
use App\Services\PlanoAulaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlanoAulaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['super_admin', 'professor'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        // Em produção a migration de permissões já concede isso ao papel
        // professor, mas ela roda antes de o papel existir num banco de
        // teste zerado — por isso concedemos aqui.
        $professor = Role::findByName('professor', 'web');
        foreach (['ViewAny', 'View', 'Create', 'Update'] as $acao) {
            $professor->givePermissionTo(Permission::firstOrCreate(['name' => "{$acao}:PlanoAula", 'guard_name' => 'web']));
        }
    }

    private function admin(): User
    {
        $user = User::factory()->create(['activated_at' => now()]);
        $user->assignRole('super_admin');

        return $user;
    }

    private function plano(array $attrs = []): PlanoAula
    {
        $turmaId = $attrs['turma_id'] ?? Turma::factory()->create()->id;
        $disciplinaId = $attrs['disciplina_id'] ?? Disciplina::factory()->create()->id;

        // O formulário de edição só reconhece a disciplina do plano como uma
        // opção válida se ela estiver vinculada à turma (turma_disciplina).
        Turma::find($turmaId)->disciplinas()->syncWithoutDetaching([$disciplinaId]);

        return PlanoAula::factory()->create(array_merge([
            'turma_id' => $turmaId,
            'disciplina_id' => $disciplinaId,
        ], $attrs));
    }

    // ─── Serviço de execução ─────────────────────────────────────────

    public function test_executar_cria_cronograma_e_marca_o_plano_como_executado(): void
    {
        $professor = Pessoa::factory()->create();
        $plano = $this->plano(['professor_id' => $professor->id, 'objetivos' => 'Ensinar frações']);
        $habilidade = Habilidade::factory()->create(['codigo' => 'EF06MA01', 'nome' => 'Resolver problemas com frações']);
        $plano->habilidades()->attach($habilidade->id);

        $cronograma = app(PlanoAulaService::class)->executar($plano);

        $this->assertInstanceOf(CronogramaAula::class, $cronograma);
        $this->assertSame($plano->turma_id, $cronograma->turma_id);
        $this->assertSame($plano->disciplina_id, $cronograma->disciplina_id);
        $this->assertSame($professor->id, $cronograma->pessoa_id);
        $this->assertSame('Ensinar frações', $cronograma->conteudo_ministrado);
        $this->assertTrue($cronograma->habilidades->contains('id', $habilidade->id));

        $plano->refresh();
        $this->assertTrue($plano->foiExecutado());
        $this->assertSame($cronograma->id, $plano->cronograma_aula_id);
    }

    public function test_executar_usa_data_prevista_quando_nenhuma_data_e_informada(): void
    {
        $plano = $this->plano(['data_prevista' => '2026-05-11']);

        $cronograma = app(PlanoAulaService::class)->executar($plano);

        $this->assertSame('2026-05-11', $cronograma->data->toDateString());
    }

    public function test_executar_aceita_data_diferente_da_prevista(): void
    {
        $plano = $this->plano(['data_prevista' => '2026-05-11']);

        $cronograma = app(PlanoAulaService::class)->executar($plano, Carbon::parse('2026-05-13'));

        $this->assertSame('2026-05-13', $cronograma->data->toDateString());
    }

    public function test_nao_e_possivel_executar_o_mesmo_plano_duas_vezes(): void
    {
        $plano = $this->plano()->fresh();
        app(PlanoAulaService::class)->executar($plano);

        $this->expectException(\InvalidArgumentException::class);
        app(PlanoAulaService::class)->executar($plano->fresh());
    }

    // ─── Recorte por professor ──────────────────────────────────────

    public function test_professor_ve_apenas_planos_das_proprias_turmas(): void
    {
        $professorPessoa = Pessoa::factory()->create();
        $professorUser = User::factory()->create(['activated_at' => now()]);
        $professorUser->assignRole('professor');
        $professorUser->pessoas()->attach($professorPessoa->id);
        session(['active_role' => 'professor']);

        $meuPlano = $this->plano(['professor_id' => $professorPessoa->id]);
        $planoAlheio = $this->plano();

        Livewire::actingAs($professorUser)
            ->test(ListPlanoAulas::class)
            ->assertCanSeeTableRecords([$meuPlano])
            ->assertCanNotSeeTableRecords([$planoAlheio]);
    }

    public function test_professor_ve_plano_de_turma_onde_e_conselheiro_mesmo_sem_ser_o_professor_do_plano(): void
    {
        $professorPessoa = Pessoa::factory()->create();
        $professorUser = User::factory()->create(['activated_at' => now()]);
        $professorUser->assignRole('professor');
        $professorUser->pessoas()->attach($professorPessoa->id);
        session(['active_role' => 'professor']);

        $turma = Turma::factory()->create(['professor_conselheiro_id' => $professorPessoa->id]);
        $plano = $this->plano(['turma_id' => $turma->id, 'professor_id' => Pessoa::factory()->create()->id]);

        Livewire::actingAs($professorUser)
            ->test(ListPlanoAulas::class)
            ->assertCanSeeTableRecords([$plano]);
    }

    // ─── Tela ────────────────────────────────────────────────────────

    public function test_acao_executar_na_tabela_gera_o_cronograma(): void
    {
        $plano = $this->plano();

        Livewire::actingAs($this->admin())
            ->test(ListPlanoAulas::class)
            ->callTableAction('executar', $plano, data: ['data' => $plano->data_prevista->toDateString()])
            ->assertNotified();

        $this->assertTrue($plano->fresh()->foiExecutado());
    }

    public function test_acao_executar_fica_oculta_para_plano_ja_executado(): void
    {
        $plano = $this->plano()->fresh();
        app(PlanoAulaService::class)->executar($plano);

        Livewire::actingAs($this->admin())
            ->test(ListPlanoAulas::class)
            ->assertTableActionHidden('executar', $plano->fresh());
    }

    public function test_editar_plano_ja_executado_e_bloqueado(): void
    {
        $plano = $this->plano()->fresh();
        app(PlanoAulaService::class)->executar($plano);

        Livewire::actingAs($this->admin())
            ->test(EditPlanoAula::class, ['record' => $plano->getKey()])
            ->fillForm(['objetivos' => 'Novo objetivo'])
            ->call('save')
            ->assertNotified();

        $this->assertNotSame('Novo objetivo', $plano->fresh()->objetivos);
    }
}
