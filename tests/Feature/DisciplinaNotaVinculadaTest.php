<?php

namespace Tests\Feature;

use App\Filament\Resources\Disciplinas\Pages\EditDisciplina;
use App\Models\AreaConhecimento;
use App\Models\Avaliacao;
use App\Models\CategoriaAvaliacao;
use App\Models\Disciplina;
use App\Models\EtapaAvaliativa;
use App\Models\Matricula;
use App\Models\Nota;
use App\Models\PeriodoLetivo;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class DisciplinaNotaVinculadaTest extends TestCase
{
    use RefreshDatabase;

    private function criarDisciplina(array $attributes = []): Disciplina
    {
        return Disciplina::factory()->create(array_merge([
            'area_id' => AreaConhecimento::create(['nome' => 'Área Teste'])->id,
        ], $attributes));
    }

    private function criarNotaParaDisciplina(Disciplina $disciplina): Nota
    {
        $turma = Turma::factory()->create();
        $periodo = PeriodoLetivo::factory()->create();

        $etapa = EtapaAvaliativa::create([
            'nome' => '1º Bimestre',
            'turma_id' => $turma->id,
            'periodo_letivo_id' => $periodo->id,
            'data_inicio' => '2026-01-01',
            'data_fim' => '2026-03-31',
        ]);

        $categoria = CategoriaAvaliacao::factory()->create(['nome' => 'Prova 1']);

        $matricula = Matricula::factory()->create(['turma_id' => $turma->id]);

        $avaliacao = Avaliacao::create([
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'etapa_avaliativa_id' => $etapa->id,
            'categoria_avaliacao_id' => $categoria->id,
            'professor_id' => null,
            'data_prevista' => now(),
            'data_ocorrencia' => now(),
            'data_limite_lancamento' => now()->addDays(15),
            'nota_maxima' => 10,
            'peso_etapa_avaliativa' => 1,
        ]);

        return Nota::create([
            'avaliacao_id' => $avaliacao->id,
            'matricula_id' => $matricula->id,
            'valor' => 8,
        ]);
    }

    public function test_possui_notas_vinculadas_retorna_false_quando_nao_ha_notas(): void
    {
        $disciplina = $this->criarDisciplina();

        $this->assertFalse($disciplina->possuiNotasVinculadas());
    }

    public function test_possui_notas_vinculadas_retorna_true_quando_ha_nota_lancada(): void
    {
        $disciplina = $this->criarDisciplina();
        $this->criarNotaParaDisciplina($disciplina);

        $this->assertTrue($disciplina->fresh()->possuiNotasVinculadas());
    }

    public function test_nao_deve_permitir_editar_disciplina_com_nota_vinculada(): void
    {
        $this->autenticarComPermissoesDisciplina();

        $disciplina = $this->criarDisciplina(['nome' => 'Matemática']);
        $this->criarNotaParaDisciplina($disciplina);

        Livewire::test(EditDisciplina::class, ['record' => $disciplina->getRouteKey()])
            ->fillForm(['nome' => 'Matemática Avançada'])
            ->call('save');

        $this->assertSame('Matemática', $disciplina->fresh()->nome);
    }

    public function test_deve_permitir_editar_disciplina_sem_nota_vinculada(): void
    {
        $this->autenticarComPermissoesDisciplina();

        $disciplina = $this->criarDisciplina(['nome' => 'Matemática']);

        Livewire::test(EditDisciplina::class, ['record' => $disciplina->getRouteKey()])
            ->fillForm(['nome' => 'Matemática Avançada'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Matemática Avançada', $disciplina->fresh()->nome);
    }

    public function test_nao_deve_permitir_excluir_disciplina_com_nota_vinculada(): void
    {
        $this->autenticarComPermissoesDisciplina();

        $disciplina = Disciplina::factory()->create();
        $this->criarNotaParaDisciplina($disciplina);

        Livewire::test(EditDisciplina::class, ['record' => $disciplina->getRouteKey()])
            ->callAction('delete');

        $this->assertNotNull($disciplina->fresh());
    }

    public function test_deve_permitir_excluir_disciplina_sem_nota_vinculada(): void
    {
        $this->autenticarComPermissoesDisciplina();

        $disciplina = Disciplina::factory()->create();

        Livewire::test(EditDisciplina::class, ['record' => $disciplina->getRouteKey()])
            ->callAction('delete');

        $this->assertNull($disciplina->fresh());
    }

    private function autenticarComPermissoesDisciplina(): void
    {
        $user = User::factory()->create([
            'activated_at' => now()->subDay(),
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user);

        Gate::define('ViewAny:Disciplina', fn () => true);
        Gate::define('View:Disciplina', fn () => true);
        Gate::define('Update:Disciplina', fn () => true);
        Gate::define('Delete:Disciplina', fn () => true);
    }
}
