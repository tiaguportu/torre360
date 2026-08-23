<?php

namespace Tests\Feature;

use App\Filament\Portal\Pages\Calendario;
use App\Models\Avaliacao;
use App\Models\CategoriaAvaliacao;
use App\Models\DiaNaoLetivo;
use App\Models\Disciplina;
use App\Models\EtapaAvaliativa;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PortalCalendarioTest extends TestCase
{
    use RefreshDatabase;

    private function criarAlunoComProva(): array
    {
        Role::firstOrCreate(['name' => 'aluno', 'guard_name' => 'web']);

        $periodo = PeriodoLetivo::factory()->create();
        $turma = Turma::factory()->create(['periodo_letivo_id' => $periodo->id]);
        $disciplina = Disciplina::factory()->create(['nome' => 'Matematica Teste']);
        $etapa = EtapaAvaliativa::create([
            'nome' => '1o Bimestre Teste',
            'periodo_letivo_id' => $periodo->id,
            'turma_id' => $turma->id,
            'data_inicio' => now(),
            'data_fim' => now()->addMonths(2),
        ]);
        $categoria = CategoriaAvaliacao::create(['nome' => 'Categoria Teste']);
        $professor = Pessoa::factory()->create();

        $pessoa = Pessoa::factory()->create(['nome' => 'Aluno Calendario Teste']);
        $user = User::factory()->create(['activated_at' => now()]);
        $pessoa->users()->save($user);
        $user->assignRole('aluno');

        Matricula::factory()->create([
            'pessoa_id' => $pessoa->id,
            'turma_id' => $turma->id,
            'periodo_letivo_id' => $periodo->id,
            'situacao' => 'ativa',
        ]);

        $prova = Avaliacao::create([
            'turma_id' => $turma->id,
            'disciplina_id' => $disciplina->id,
            'etapa_avaliativa_id' => $etapa->id,
            'categoria_avaliacao_id' => $categoria->id,
            'professor_id' => $professor->id,
            'data_prevista' => now()->addDays(5),
            'data_ocorrencia' => now()->addDays(5),
            'data_limite_lancamento' => now()->addDays(20),
            'nota_maxima' => 10,
            'peso_etapa_avaliativa' => 1,
        ]);

        $feriado = DiaNaoLetivo::create([
            'periodo_letivo_id' => $periodo->id,
            'data' => now()->addDays(10)->toDateString(),
            'descricao' => 'Feriado Teste',
            'flag_ativo' => true,
        ]);

        return compact('user', 'pessoa', 'turma', 'periodo', 'prova', 'feriado');
    }

    public function test_aluno_ve_calendario_do_portal(): void
    {
        $dados = $this->criarAlunoComProva();

        $this->actingAs($dados['user'])
            ->get('/portal/calendario')
            ->assertOk();
    }

    public function test_aluno_ve_prova_e_feriado_da_propria_turma_no_calendario(): void
    {
        $dados = $this->criarAlunoComProva();

        $events = Livewire::actingAs($dados['user'])
            ->test(Calendario::class)
            ->instance()
            ->getEvents();

        $ids = collect($events)->pluck('id');

        $this->assertTrue($ids->contains('aval-'.$dados['prova']->id));
        $this->assertTrue($ids->contains('feriado-'.$dados['feriado']->id));
    }

    public function test_aluno_nao_ve_prova_de_turma_de_outro_aluno_no_calendario(): void
    {
        $dados = $this->criarAlunoComProva();

        $outraPeriodo = PeriodoLetivo::factory()->create();
        $outraTurma = Turma::factory()->create(['periodo_letivo_id' => $outraPeriodo->id]);
        $disciplina = Disciplina::factory()->create();
        $etapa = EtapaAvaliativa::create([
            'nome' => 'Etapa Outra Turma',
            'periodo_letivo_id' => $outraPeriodo->id,
            'turma_id' => $outraTurma->id,
            'data_inicio' => now(),
            'data_fim' => now()->addMonths(2),
        ]);
        $categoria = CategoriaAvaliacao::create(['nome' => 'Categoria Teste']);
        $professor = Pessoa::factory()->create();

        $provaAlheia = Avaliacao::create([
            'turma_id' => $outraTurma->id,
            'disciplina_id' => $disciplina->id,
            'etapa_avaliativa_id' => $etapa->id,
            'categoria_avaliacao_id' => $categoria->id,
            'professor_id' => $professor->id,
            'data_prevista' => now()->addDays(5),
            'data_ocorrencia' => now()->addDays(5),
            'data_limite_lancamento' => now()->addDays(20),
            'nota_maxima' => 10,
            'peso_etapa_avaliativa' => 1,
        ]);

        $feriadoAlheio = DiaNaoLetivo::create([
            'periodo_letivo_id' => $outraPeriodo->id,
            'data' => now()->addDays(10)->toDateString(),
            'descricao' => 'Feriado Outra Turma',
            'flag_ativo' => true,
        ]);

        $events = Livewire::actingAs($dados['user'])
            ->test(Calendario::class)
            ->instance()
            ->getEvents();

        $ids = collect($events)->pluck('id');

        $this->assertFalse($ids->contains('aval-'.$provaAlheia->id));
        $this->assertFalse($ids->contains('feriado-'.$feriadoAlheio->id));
    }
}
