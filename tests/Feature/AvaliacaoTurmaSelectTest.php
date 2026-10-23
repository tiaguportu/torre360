<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatusTurma;
use App\Filament\Resources\Avaliacaos\Pages\CreateAvaliacao;
use App\Filament\Resources\Avaliacaos\Pages\EditAvaliacao;
use App\Models\Avaliacao;
use App\Models\CategoriaAvaliacao;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\EtapaAvaliativa;
use App\Models\PeriodoLetivo;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\Turno;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AvaliacaoTurmaSelectTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private PeriodoLetivo $periodo2026;

    private PeriodoLetivo $periodo2027;

    private Turma $turma2026;

    private Turma $turma2027;

    private Turma $turmaConcluida;

    private Turma $turmaCancelada;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        foreach ([
            'ViewAny:Avaliacao',
            'View:Avaliacao',
            'Create:Avaliacao',
            'Update:Avaliacao',
            'Delete:Avaliacao',
        ] as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }

        $this->admin = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $this->admin->assignRole('super_admin');
        session(['active_role' => 'super_admin']);

        $unidade = Unidade::create(['nome' => 'Unidade Teste']);
        $curso = Curso::create(['nome_externo' => 'Infantil', 'nome_interno' => 'Infantil', 'unidade_id' => $unidade->id]);
        $serie = Serie::create(['nome' => 'Berçário II', 'curso_id' => $curso->id, 'sistema_avaliacao' => 'Nota']);
        $turno = Turno::create(['nome' => 'Manhã', 'hora_inicio' => '07:30', 'hora_fim' => '12:00']);

        $this->periodo2026 = PeriodoLetivo::create([
            'nome' => '2026',
            'data_inicio' => '2026-02-01',
            'data_fim' => '2026-12-15',
        ]);

        $this->periodo2027 = PeriodoLetivo::create([
            'nome' => '2027',
            'data_inicio' => '2027-02-01',
            'data_fim' => '2027-12-15',
        ]);

        $this->turma2026 = Turma::create([
            'nome' => 'Berçário II',
            'serie_id' => $serie->id,
            'turno_id' => $turno->id,
            'periodo_letivo_id' => $this->periodo2026->id,
            'status' => StatusTurma::Ativa->value,
        ]);

        $this->turma2027 = Turma::create([
            'nome' => 'Berçário II',
            'serie_id' => $serie->id,
            'turno_id' => $turno->id,
            'periodo_letivo_id' => $this->periodo2027->id,
            'status' => StatusTurma::Planejada->value,
        ]);

        $this->turmaConcluida = Turma::create([
            'nome' => 'Berçário II Concluída',
            'serie_id' => $serie->id,
            'turno_id' => $turno->id,
            'periodo_letivo_id' => $this->periodo2026->id,
            'status' => StatusTurma::Concluida->value,
        ]);

        $this->turmaCancelada = Turma::create([
            'nome' => 'Berçário II Cancelada',
            'serie_id' => $serie->id,
            'turno_id' => $turno->id,
            'periodo_letivo_id' => $this->periodo2026->id,
            'status' => StatusTurma::Cancelada->value,
        ]);
    }

    public function test_select_turmas_no_create_exibe_periodo_e_nao_exibe_turmas_encerradas(): void
    {
        $component = Livewire::actingAs($this->admin)
            ->test(CreateAvaliacao::class);

        $opcoes = $component->instance()->getSchema('form')->getFlatFields(withHidden: true)['turma_id']->getOptions();

        $this->assertArrayHasKey($this->turma2026->id, $opcoes);
        $this->assertArrayHasKey($this->turma2027->id, $opcoes);
        $this->assertArrayNotHasKey($this->turmaConcluida->id, $opcoes);
        $this->assertArrayNotHasKey($this->turmaCancelada->id, $opcoes);

        $this->assertSame('Berçário II - Manhã (2026)', $opcoes[$this->turma2026->id]);
        $this->assertSame('Berçário II - Manhã (2027)', $opcoes[$this->turma2027->id]);
    }

    public function test_select_turmas_no_edit_nao_exibe_turmas_encerradas(): void
    {
        $disciplina = Disciplina::create(['nome' => 'Desenvolvimento Motor']);
        $categoria = CategoriaAvaliacao::create(['nome' => 'Avaliação Diagnóstica']);
        $etapa = EtapaAvaliativa::create([
            'nome' => '1º Bimestre',
            'turma_id' => $this->turma2026->id,
            'periodo_letivo_id' => $this->periodo2026->id,
            'data_inicio' => '2026-02-01',
            'data_fim' => '2026-04-30',
        ]);

        $avaliacao = Avaliacao::create([
            'turma_id' => $this->turma2026->id,
            'disciplina_id' => $disciplina->id,
            'categoria_avaliacao_id' => $categoria->id,
            'etapa_avaliativa_id' => $etapa->id,
            'data_prevista' => '2026-03-15',
            'data_ocorrencia' => '2026-03-15',
            'data_limite_lancamento' => '2026-03-30',
            'nota_maxima' => 10.00,
            'peso_etapa_avaliativa' => 1.00,
        ]);

        $component = Livewire::actingAs($this->admin)
            ->test(EditAvaliacao::class, ['record' => $avaliacao->getKey()]);

        $opcoes = $component->instance()->getSchema('form')->getFlatFields(withHidden: true)['turma_id']->getOptions();

        $this->assertArrayHasKey($this->turma2026->id, $opcoes);
        $this->assertArrayHasKey($this->turma2027->id, $opcoes);
        $this->assertArrayNotHasKey($this->turmaConcluida->id, $opcoes);
        $this->assertArrayNotHasKey($this->turmaCancelada->id, $opcoes);

        $this->assertSame('Berçário II - Manhã (2026)', $opcoes[$this->turma2026->id]);
        $this->assertSame('Berçário II - Manhã (2027)', $opcoes[$this->turma2027->id]);
    }

    public function test_selecionar_turma_limpa_disciplina_e_etapa(): void
    {
        $disciplina = Disciplina::create(['nome' => 'Música']);
        $this->turma2026->disciplinas()->attach($disciplina->id);

        $component = Livewire::actingAs($this->admin)
            ->test(CreateAvaliacao::class)
            ->set('data.turma_id', $this->turma2026->id)
            ->set('data.disciplina_id', $disciplina->id)
            ->set('data.turma_id', $this->turma2027->id);

        $component->assertSet('data.disciplina_id', null);
        $component->assertSet('data.etapa_avaliativa_id', null);
    }
}
