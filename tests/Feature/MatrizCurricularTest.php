<?php

namespace Tests\Feature;

use App\Filament\Resources\Series\Pages\EditSerie;
use App\Filament\Resources\Series\RelationManagers\MatrizCurricularRelationManager;
use App\Filament\Resources\Turmas\Pages\CreateTurma;
use App\Filament\Resources\Turmas\Pages\EditTurma;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\MatrizCurricular;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\Turno;
use App\Models\Unidade;
use App\Models\User;
use App\Services\MatrizCurricularService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MatrizCurricularTest extends TestCase
{
    use RefreshDatabase;

    private Serie $serie;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $unidade = Unidade::create(['nome' => 'Unidade Teste']);
        $curso = Curso::create([
            'unidade_id' => $unidade->id,
            'nome_externo' => 'Curso Teste',
            'nome_interno' => 'Curso Teste',
        ]);
        $this->serie = Serie::create([
            'nome' => '6º Ano',
            'curso_id' => $curso->id,
            'sistema_avaliacao' => 'Nota',
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['activated_at' => now()]);
        $user->assignRole('super_admin');

        return $user;
    }

    public function test_sincronizar_adiciona_disciplinas_faltantes_e_e_idempotente(): void
    {
        $matematica = Disciplina::factory()->create(['nome' => 'Matemática']);
        $portugues = Disciplina::factory()->create(['nome' => 'Português']);
        MatrizCurricular::factory()->create(['serie_id' => $this->serie->id, 'disciplina_id' => $matematica->id]);
        MatrizCurricular::factory()->create(['serie_id' => $this->serie->id, 'disciplina_id' => $portugues->id]);

        $turma = Turma::factory()->create(['serie_id' => $this->serie->id]);

        $total = app(MatrizCurricularService::class)->sincronizarTurmaDisciplinas($turma);

        $this->assertSame(2, $total);
        $this->assertEqualsCanonicalizing(
            [$matematica->id, $portugues->id],
            $turma->disciplinas()->pluck('disciplina.id')->all()
        );

        // Rodar de novo não duplica.
        $totalSegundaVez = app(MatrizCurricularService::class)->sincronizarTurmaDisciplinas($turma);
        $this->assertSame(0, $totalSegundaVez);
        $this->assertCount(2, $turma->disciplinas()->get());
    }

    public function test_sincronizar_nao_remove_disciplina_vinculada_manualmente_fora_da_matriz(): void
    {
        $historia = Disciplina::factory()->create(['nome' => 'História']);
        $ciencias = Disciplina::factory()->create(['nome' => 'Ciências']);
        MatrizCurricular::factory()->create(['serie_id' => $this->serie->id, 'disciplina_id' => $ciencias->id]);

        $turma = Turma::factory()->create(['serie_id' => $this->serie->id]);
        $turma->disciplinas()->attach($historia->id);

        app(MatrizCurricularService::class)->sincronizarTurmaDisciplinas($turma);

        $this->assertEqualsCanonicalizing(
            [$historia->id, $ciencias->id],
            $turma->disciplinas()->pluck('disciplina.id')->all()
        );
    }

    public function test_turma_sem_serie_ou_sem_matriz_nao_gera_erro(): void
    {
        $turmaSemSerie = Turma::factory()->create(['serie_id' => null]);
        $this->assertSame(0, app(MatrizCurricularService::class)->sincronizarTurmaDisciplinas($turmaSemSerie));

        $serieSemMatriz = Serie::create([
            'nome' => '7º Ano',
            'curso_id' => $this->serie->curso_id,
            'sistema_avaliacao' => 'Nota',
        ]);
        $turmaSemMatriz = Turma::factory()->create(['serie_id' => $serieSemMatriz->id]);
        $this->assertSame(0, app(MatrizCurricularService::class)->sincronizarTurmaDisciplinas($turmaSemMatriz));
    }

    public function test_criar_turma_pelo_admin_sincroniza_automaticamente(): void
    {
        $disciplina = Disciplina::factory()->create(['nome' => 'Artes']);
        MatrizCurricular::factory()->create(['serie_id' => $this->serie->id, 'disciplina_id' => $disciplina->id]);

        Livewire::actingAs($this->admin())
            ->test(CreateTurma::class)
            ->fillForm([
                'nome' => 'Turma A',
                'serie_id' => $this->serie->id,
                'turno_id' => Turno::create(['nome' => 'Manhã', 'hora_inicio' => '07:00', 'hora_fim' => '12:00'])->id,
                'tipo_avaliacao' => 'notas',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $turma = Turma::where('nome', 'Turma A')->firstOrFail();
        $this->assertTrue($turma->disciplinas->contains('id', $disciplina->id));
    }

    public function test_acao_sincronizar_no_edit_turma_adiciona_disciplinas_novas(): void
    {
        $disciplina = Disciplina::factory()->create();
        $turma = Turma::factory()->create(['serie_id' => $this->serie->id]);
        MatrizCurricular::factory()->create(['serie_id' => $this->serie->id, 'disciplina_id' => $disciplina->id]);

        Livewire::actingAs($this->admin())
            ->test(EditTurma::class, ['record' => $turma->getKey()])
            ->callAction('sincronizarMatrizCurricular')
            ->assertNotified();

        $this->assertTrue($turma->fresh()->disciplinas->contains('id', $disciplina->id));
    }

    public function test_relation_manager_lista_e_cria_itens_da_matriz(): void
    {
        $disciplina = Disciplina::factory()->create(['nome' => 'Educação Física']);
        $item = MatrizCurricular::factory()->create(['serie_id' => $this->serie->id, 'disciplina_id' => $disciplina->id]);

        Livewire::actingAs($this->admin())
            ->test(MatrizCurricularRelationManager::class, ['ownerRecord' => $this->serie, 'pageClass' => EditSerie::class])
            ->assertCanSeeTableRecords([$item])
            ->assertSee('Educação Física');
    }
}
