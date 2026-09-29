<?php

namespace Tests\Feature;

use App\Filament\Pages\LancamentoNotasGrade;
use App\Filament\Resources\CronogramaAulas\Pages\LancarFrequencia;
use App\Models\Avaliacao;
use App\Models\CategoriaAvaliacao;
use App\Models\CronogramaAula;
use App\Models\Disciplina;
use App\Models\EtapaAvaliativa;
use App\Models\FrequenciaEscolar;
use App\Models\Matricula;
use App\Models\Nota;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Garante que as telas de lançamento do professor (ajustadas para telas
 * pequenas) continuam renderizando e salvando normalmente.
 */
class ProfessorTelasMobileTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Turma $turma;

    private Disciplina $disciplina;

    private Matricula $matricula;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create(['activated_at' => now(), 'email_verified_at' => now()]);
        $this->admin->assignRole('super_admin');

        $periodo = PeriodoLetivo::factory()->create();
        $this->turma = Turma::factory()->create(['periodo_letivo_id' => $periodo->id]);
        $this->disciplina = Disciplina::factory()->create(['nome' => 'Matematica Mobile']);
        $aluno = Pessoa::factory()->create(['nome' => 'Aluno Mobile Teste']);

        $this->matricula = Matricula::factory()->create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $this->turma->id,
            'periodo_letivo_id' => $periodo->id,
            'situacao' => 'ativa',
        ]);
    }

    public function test_grade_de_notas_carrega_o_aluno_e_salva_a_nota(): void
    {
        $etapa = EtapaAvaliativa::create([
            'nome' => '1º Bimestre',
            'periodo_letivo_id' => $this->matricula->periodo_letivo_id,
            'turma_id' => $this->turma->id,
            'data_inicio' => now()->subMonth()->toDateString(),
            'data_fim' => now()->addMonth()->toDateString(),
        ]);
        $avaliacao = Avaliacao::create([
            'turma_id' => $this->turma->id,
            'disciplina_id' => $this->disciplina->id,
            'etapa_avaliativa_id' => $etapa->id,
            'categoria_avaliacao_id' => CategoriaAvaliacao::create(['nome' => 'Prova'])->id,
            'professor_id' => Pessoa::factory()->create()->id,
            'data_prevista' => now(),
            'data_ocorrencia' => now(),
            'data_limite_lancamento' => now()->addDays(20),
            'nota_maxima' => 10,
            'peso_etapa_avaliativa' => 1,
        ]);

        $pagina = Livewire::actingAs($this->admin)
            ->test(LancamentoNotasGrade::class)
            ->set('data.turma_id', $this->turma->id)
            ->set('data.disciplina_id', $this->disciplina->id)
            ->set('data.avaliacao_id', $avaliacao->id)
            ->call('carregarGrade');

        $notas = $pagina->get('data.notas');
        $this->assertSame(['Aluno Mobile Teste'], array_column($notas, 'aluno_nome'));

        $chave = array_key_first($notas);

        $pagina->set("data.notas.{$chave}.valor", 8.5)->call('salvarNotas');

        $this->assertSame(8.5, (float) Nota::where('matricula_id', $this->matricula->id)->where('avaliacao_id', $avaliacao->id)->value('valor'));
    }

    public function test_chamada_rapida_lista_o_aluno_e_mantem_o_toggle_de_presenca(): void
    {
        $aula = CronogramaAula::factory()->create([
            'turma_id' => $this->turma->id,
            'disciplina_id' => $this->disciplina->id,
            'pessoa_id' => Pessoa::factory()->create()->id,
            'data' => now()->toDateString(),
        ]);
        FrequenciaEscolar::create([
            'matricula_id' => $this->matricula->id,
            'cronograma_aula_id' => $aula->id,
            'situacao' => 'ausente',
        ]);

        Livewire::actingAs($this->admin)
            ->test(LancarFrequencia::class, ['record' => $aula->getKey()])
            ->assertSee('Presente')
            ->assertSee('Ausente')
            ->callAction('darPresencaTodos')
            ->assertHasNoErrors();

        $this->assertSame('presente', FrequenciaEscolar::where('matricula_id', $this->matricula->id)->value('situacao'));
    }
}
