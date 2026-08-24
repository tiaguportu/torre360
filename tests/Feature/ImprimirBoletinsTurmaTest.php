<?php

namespace Tests\Feature;

use App\Filament\Resources\Turmas\Pages\ListTurmas;
use App\Jobs\GerarBoletinsTurmaPdfJob;
use App\Models\Avaliacao;
use App\Models\Disciplina;
use App\Models\EtapaAvaliativa;
use App\Models\Matricula;
use App\Models\Nota;
use App\Models\PeriodoLetivo;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ImprimirBoletinsTurmaTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private Turma $turma;

    private EtapaAvaliativa $etapa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'activated_at' => now()->subDay(),
            'deactivated_at' => null,
            'email_verified_at' => now(),
        ]);
        $role = Role::firstOrCreate(['name' => 'super_admin']);
        $this->adminUser->assignRole($role);
        session(['active_role' => 'super_admin']);

        $periodo = PeriodoLetivo::factory()->create();
        $this->turma = Turma::factory()->create([
            'periodo_letivo_id' => $periodo->id,
        ]);

        $this->etapa = EtapaAvaliativa::create([
            'nome' => '1ª Etapa Teste',
            'periodo_letivo_id' => $periodo->id,
            'turma_id' => $this->turma->id,
            'data_inicio' => now()->subMonths(2)->toDateString(),
            'data_fim' => now()->addMonth()->toDateString(),
        ]);
    }

    private function criarTurmaComNotas(): Turma
    {
        $turma = Turma::factory()->create([
            'periodo_letivo_id' => $this->etapa->periodo_letivo_id,
        ]);
        $matricula = Matricula::factory()->create([
            'turma_id' => $turma->id,
            'situacao' => 'ativa',
        ]);
        $avaliacao = Avaliacao::create([
            'turma_id' => $turma->id,
            'disciplina_id' => Disciplina::factory()->create()->id,
            'etapa_avaliativa_id' => $this->etapa->id,
            'peso_etapa_avaliativa' => 1.0,
            'nota_maxima' => 10.0,
            'data_ocorrencia' => now()->toDateString(),
            'data_limite_lancamento' => now()->addDays(5)->toDateString(),
        ]);
        Nota::create([
            'avaliacao_id' => $avaliacao->id,
            'matricula_id' => $matricula->id,
            'valor' => 8.5,
        ]);

        return $turma;
    }

    public function test_acao_imprimir_boletins_despacha_job_em_fila(): void
    {
        Bus::fake();

        $turma = $this->criarTurmaComNotas();

        Livewire::actingAs($this->adminUser)
            ->test(ListTurmas::class)
            ->callTableAction('imprimirBoletins', $turma, data: ['etapa_id' => $this->etapa->id])
            ->assertHasNoTableActionErrors();

        Bus::assertDispatched(GerarBoletinsTurmaPdfJob::class, function (GerarBoletinsTurmaPdfJob $job) use ($turma) {
            return $job->turmaIds === [$turma->id]
                && $job->etapaId === $this->etapa->id
                && $job->userId === $this->adminUser->id;
        });
    }

    public function test_acao_em_lote_imprimir_boletins_despacha_job_com_todas_as_turmas_selecionadas(): void
    {
        Bus::fake();

        $turma1 = $this->criarTurmaComNotas();
        $turma2 = $this->criarTurmaComNotas();

        Livewire::actingAs($this->adminUser)
            ->test(ListTurmas::class)
            ->callTableBulkAction('imprimirBoletinsLote', [$turma1, $turma2], data: ['etapa_id' => $this->etapa->id]);

        Bus::assertDispatched(GerarBoletinsTurmaPdfJob::class, function (GerarBoletinsTurmaPdfJob $job) use ($turma1, $turma2) {
            return $job->turmaIds === [$turma1->id, $turma2->id]
                && $job->etapaId === $this->etapa->id
                && $job->userId === $this->adminUser->id;
        });
    }

    public function test_tela_de_listagem_de_turmas_exibe_botao_imprimir_apenas_se_houver_notas(): void
    {
        $this->actingAs($this->adminUser);

        $turmaComNotas = $this->criarTurmaComNotas();

        Livewire::test(ListTurmas::class)
            ->assertStatus(200)
            ->assertTableActionVisible('imprimirBoletins', $turmaComNotas)
            ->assertTableActionHidden('imprimirBoletins', $this->turma)
            ->assertTableBulkActionExists('imprimirBoletinsLote');
    }
}
