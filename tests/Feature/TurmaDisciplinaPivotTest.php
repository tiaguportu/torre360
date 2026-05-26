<?php

namespace Tests\Feature;

use App\Filament\Resources\Turmas\Pages\EditTurma;
use App\Filament\Resources\Turmas\RelationManagers\DisciplinasRelationManager;
use App\Models\Disciplina;
use App\Models\Pessoa;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TurmaDisciplinaPivotTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Autentica um usuário super_admin para passar nas permissões do Shield
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user->assignRole($role);

        $this->actingAs($user);
    }

    private function createTurma(): Turma
    {
        $turma = new Turma;
        $turma->nome = 'Turma Teste';
        $turma->save();

        return $turma;
    }

    private function createDisciplina(string $nome = 'Disciplina Teste'): Disciplina
    {
        $disciplina = new Disciplina;
        $disciplina->nome = $nome;
        $disciplina->save();

        return $disciplina;
    }

    public function test_pode_renderizar_relation_manager_de_disciplinas(): void
    {
        $turma = $this->createTurma();

        Livewire::test(DisciplinasRelationManager::class, [
            'ownerRecord' => $turma,
            'pageClass' => EditTurma::class,
        ])
            ->assertSuccessful();
    }

    public function test_pode_vincular_disciplina_existente_com_professor_no_pivot(): void
    {
        $turma = $this->createTurma();
        $disciplina = $this->createDisciplina();
        $professor = Pessoa::factory()->create();

        Livewire::test(DisciplinasRelationManager::class, [
            'ownerRecord' => $turma,
            'pageClass' => EditTurma::class,
        ])
            ->callTableAction('attach', null, [
                'recordId' => $disciplina->id,
                'professor_id' => $professor->id,
            ])
            ->assertHasNoTableActionErrors();

        // Verifica se foi salvo no pivot da turma
        $this->assertDatabaseHas('turma_disciplina', [
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'professor_id' => $professor->id,
        ]);
    }

    public function test_pode_criar_nova_disciplina_e_vincular_com_professor_no_pivot(): void
    {
        $turma = $this->createTurma();
        $professor = Pessoa::factory()->create();

        Livewire::test(DisciplinasRelationManager::class, [
            'ownerRecord' => $turma,
            'pageClass' => EditTurma::class,
        ])
            ->callTableAction('create', null, [
                'nome' => 'Nova Disciplina de Teste',
                'professor_id' => $professor->id,
            ])
            ->assertHasNoTableActionErrors();

        // Verifica se a disciplina foi criada
        $disciplina = Disciplina::where('nome', 'Nova Disciplina de Teste')->first();
        $this->assertNotNull($disciplina);

        // Verifica se o pivot foi preenchido corretamente
        $this->assertDatabaseHas('turma_disciplina', [
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'professor_id' => $professor->id,
        ]);
    }

    public function test_pode_editar_apenas_o_professor_responsavel_no_pivot(): void
    {
        $turma = $this->createTurma();
        $disciplina = $this->createDisciplina();
        $professorAntigo = Pessoa::factory()->create();
        $professorNovo = Pessoa::factory()->create();

        // Vincula inicialmente com o professor antigo
        $turma->disciplinas()->attach($disciplina->id, [
            'professor_id' => $professorAntigo->id,
        ]);

        Livewire::test(DisciplinasRelationManager::class, [
            'ownerRecord' => $turma,
            'pageClass' => EditTurma::class,
        ])
            ->callTableAction('edit', $disciplina, [
                'professor_id' => $professorNovo->id,
            ])
            ->assertHasNoTableActionErrors();

        // Verifica se o pivot foi atualizado
        $this->assertDatabaseHas('turma_disciplina', [
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'professor_id' => $professorNovo->id,
        ]);

        // Garante que o professor antigo não é mais o responsável por essa disciplina na turma
        $this->assertDatabaseMissing('turma_disciplina', [
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'professor_id' => $professorAntigo->id,
        ]);
    }
}
