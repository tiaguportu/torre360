<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatusTurma;
use App\Filament\Resources\Turmas\Pages\ListTurmas;
use App\Models\CampoExperiencia;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Habilidade;
use App\Models\PeriodoLetivo;
use App\Models\Serie;
use App\Models\TipoDocumento;
use App\Models\Turma;
use App\Models\TurmaHorario;
use App\Models\Turno;
use App\Models\Unidade;
use App\Models\User;
use App\Services\TurmaDuplicacaoService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TurmaStatusTest extends TestCase
{
    use RefreshDatabase;

    private Serie $serie;

    private Turno $turno;

    protected function setUp(): void
    {
        parent::setUp();

        $unidade = Unidade::create(['nome' => 'Sede']);
        $curso = Curso::create(['nome_externo' => 'Fundamental', 'nome_interno' => 'Fundamental', 'unidade_id' => $unidade->id]);
        $this->serie = Serie::create(['nome' => '1º Ano', 'curso_id' => $curso->id, 'sistema_avaliacao' => 'Nota']);
        $this->turno = Turno::create(['nome' => 'Manhã', 'hora_inicio' => '07:30', 'hora_fim' => '12:00']);
    }

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::factory()->create(['activated_at' => now()]);
        $admin->assignRole('super_admin');
        session(['active_role' => 'super_admin']);

        return $admin;
    }

    public function test_turma_nasce_ativa_e_status_e_um_enum(): void
    {
        $turma = Turma::factory()->create();

        $this->assertSame(StatusTurma::Ativa, $turma->fresh()->status);
        $this->assertSame('Ativa', $turma->status->getLabel());
    }

    public function test_status_aberto_para_matricula_so_inclui_planejada_e_ativa(): void
    {
        $this->assertTrue(StatusTurma::Planejada->abertaParaMatricula());
        $this->assertTrue(StatusTurma::Ativa->abertaParaMatricula());
        $this->assertFalse(StatusTurma::Concluida->abertaParaMatricula());
        $this->assertFalse(StatusTurma::Cancelada->abertaParaMatricula());
    }

    public function test_scopes_filtram_por_status_e_mantem_a_turma_atual_do_registro(): void
    {
        $planejada = Turma::factory()->planejada()->create();
        $ativa = Turma::factory()->create();
        $concluida = Turma::factory()->concluida()->create();
        $cancelada = Turma::factory()->cancelada()->create();

        $this->assertEqualsCanonicalizing([$ativa->id], Turma::ativas()->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$planejada->id, $ativa->id], Turma::abertasParaMatricula()->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$planejada->id, $ativa->id], Turma::vigentes()->pluck('id')->all());

        // Editando uma matrícula antiga cuja turma já foi concluída, ela continua selecionável.
        $this->assertEqualsCanonicalizing(
            [$planejada->id, $ativa->id, $concluida->id],
            Turma::abertasParaMatricula([$concluida->id, null])->pluck('id')->all()
        );
        $this->assertNotContains($cancelada->id, Turma::abertasParaMatricula([$concluida->id])->pluck('id')->all());
    }

    public function test_duplicar_cria_turma_planejada_no_novo_periodo_copiando_a_estrutura(): void
    {
        $periodo2026 = PeriodoLetivo::factory()->create(['nome' => '2026']);
        $periodo2027 = PeriodoLetivo::factory()->create(['nome' => '2027', 'data_inicio' => '2027-01-01', 'data_fim' => '2027-12-31']);

        $origem = Turma::factory()->create([
            'nome' => '1º Ano A',
            'serie_id' => $this->serie->id,
            'turno_id' => $this->turno->id,
            'periodo_letivo_id' => $periodo2026->id,
            'vagas_maximas' => 28,
        ]);

        $disciplina = Disciplina::factory()->create();
        $origem->disciplinas()->attach($disciplina->id);
        $habilidade = Habilidade::factory()->create([
            'codigo' => 'EF01LP01',
            'nome' => 'Reconhecer letras e sons.',
            'campo_experiencia_id' => CampoExperiencia::create(['nome' => 'Escuta, fala e pensamento'])->id,
            'tipo' => 'BNCC',
        ]);
        $origem->habilidades()->attach($habilidade->id);
        $tipoDocumento = TipoDocumento::create(['nome' => 'RG']);
        $origem->tiposDocumentos()->attach($tipoDocumento->id);
        TurmaHorario::create(['turma_id' => $origem->id, 'dia_semana' => 1, 'hora_inicio' => '07:30', 'hora_fim' => '12:00']);

        $nova = app(TurmaDuplicacaoService::class)->duplicar($origem, $periodo2027);

        $this->assertNotNull($nova);
        $this->assertNotSame($origem->id, $nova->id);
        $this->assertSame($periodo2027->id, $nova->periodo_letivo_id);
        $this->assertSame(StatusTurma::Planejada, $nova->status);
        $this->assertSame('1º Ano A', $nova->nome);
        $this->assertSame(28, (int) $nova->vagas_maximas);
        $this->assertSame($this->serie->id, $nova->serie_id);
        $this->assertSame($this->turno->id, $nova->turno_id);
        $this->assertSame([$disciplina->id], $nova->disciplinas()->pluck('disciplina.id')->all());
        $this->assertSame([$habilidade->id], $nova->habilidades()->pluck('habilidades.id')->all());
        $this->assertSame([$tipoDocumento->id], $nova->tiposDocumentos()->pluck('tipo_documento.id')->all());
        $this->assertSame(1, $nova->horariosFuncionamento()->count());
        $this->assertSame(0, $nova->matriculas()->count());

        // A turma de origem não muda.
        $this->assertSame($periodo2026->id, $origem->fresh()->periodo_letivo_id);
        $this->assertSame(StatusTurma::Ativa, $origem->fresh()->status);
    }

    public function test_duplicar_duas_vezes_ou_para_o_proprio_periodo_nao_cria_turma(): void
    {
        $periodo2026 = PeriodoLetivo::factory()->create();
        $periodo2027 = PeriodoLetivo::factory()->create(['nome' => '2027']);
        $origem = Turma::factory()->create([
            'serie_id' => $this->serie->id,
            'turno_id' => $this->turno->id,
            'periodo_letivo_id' => $periodo2026->id,
        ]);
        $servico = app(TurmaDuplicacaoService::class);

        $this->assertNull($servico->duplicar($origem, $periodo2026), 'Mesmo período não duplica.');
        $this->assertNotNull($servico->duplicar($origem, $periodo2027));
        $this->assertNull($servico->duplicar($origem, $periodo2027), 'Segunda execução não duplica.');
        $this->assertSame(2, Turma::count());
    }

    public function test_acao_em_lote_duplica_turmas_para_outro_periodo(): void
    {
        $periodo2026 = PeriodoLetivo::factory()->create();
        $periodo2027 = PeriodoLetivo::factory()->create(['nome' => '2027']);
        $turmas = Turma::factory()->count(2)->create([
            'serie_id' => $this->serie->id,
            'turno_id' => $this->turno->id,
            'periodo_letivo_id' => $periodo2026->id,
        ]);

        Livewire::actingAs($this->admin())
            ->test(ListTurmas::class)
            ->callTableBulkAction('duplicarParaPeriodo', $turmas, data: [
                'periodo_letivo_id' => $periodo2027->id,
                'status' => StatusTurma::Planejada->value,
            ])
            ->assertHasNoTableBulkActionErrors()
            ->assertNotified();

        $this->assertSame(2, Turma::where('periodo_letivo_id', $periodo2027->id)->where('status', 'planejada')->count());
    }

    public function test_acao_em_lote_altera_o_status_das_turmas(): void
    {
        $turmas = Turma::factory()->count(2)->create();
        $outra = Turma::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ListTurmas::class)
            ->callTableBulkAction('alterarStatusLote', $turmas, data: ['status' => StatusTurma::Concluida->value])
            ->assertHasNoTableBulkActionErrors()
            ->assertNotified();

        $this->assertSame(2, Turma::where('status', 'concluida')->count());
        $this->assertSame(StatusTurma::Ativa, $outra->fresh()->status);
    }

    public function test_lista_filtra_por_status_e_exibe_periodo(): void
    {
        $ativa = Turma::factory()->create();
        $concluida = Turma::factory()->concluida()->create();

        Livewire::actingAs($this->admin())
            ->test(ListTurmas::class)
            ->assertCanSeeTableRecords([$ativa, $concluida])
            ->filterTable('status', [StatusTurma::Concluida->value])
            ->assertCanSeeTableRecords([$concluida])
            ->assertCanNotSeeTableRecords([$ativa]);
    }

    public function test_periodo_letivo_da_turma_e_obrigatorio_no_banco(): void
    {
        $this->expectException(QueryException::class);

        DB::table('turma')->insert(['nome' => 'Sem período', 'periodo_letivo_id' => null]);
    }

    public function test_migration_aborta_quando_existe_turma_sem_periodo(): void
    {
        $migration = require base_path('database/migrations/2026_10_08_100000_add_status_and_require_periodo_in_turma_table.php');

        // Estado "antigo": período volta a ser opcional e existe uma turma órfã.
        $migration->down();
        DB::table('turma')->insert(['nome' => 'Órfã', 'periodo_letivo_id' => null]);

        try {
            $migration->up();
            $this->fail('A migration deveria abortar com turmas sem período.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('1 turma(s) sem período', $e->getMessage());
        }

        // Nada foi alterado: a coluna continua aceitando nulo até a turma ser vinculada.
        DB::table('turma')->whereNull('periodo_letivo_id')->update(['periodo_letivo_id' => PeriodoLetivo::factory()->create()->id]);
        $migration->up();

        $this->assertTrue(Schema::hasColumn('turma', 'status'));
    }
}
