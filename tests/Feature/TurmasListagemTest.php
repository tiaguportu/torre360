<?php

namespace Tests\Feature;

use App\Enums\StatusTurma;
use App\Filament\Resources\Turmas\Pages\ListTurmas;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TurmasListagemTest extends TestCase
{
    use RefreshDatabase;

    private PeriodoLetivo $periodoAnterior;

    private PeriodoLetivo $periodoVigente;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::factory()->create([
            'activated_at' => now()->subDay(),
            'deactivated_at' => null,
            'email_verified_at' => now(),
        ]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'super_admin']));
        session(['active_role' => 'super_admin']);
        $this->actingAs($admin);

        $this->periodoAnterior = PeriodoLetivo::factory()->create([
            'nome' => 'Ano Anterior',
            'data_inicio' => now()->subYear()->startOfYear()->toDateString(),
            'data_fim' => now()->subYear()->endOfYear()->toDateString(),
        ]);
        $this->periodoVigente = PeriodoLetivo::factory()->create([
            'nome' => 'Ano Vigente',
            'data_inicio' => now()->startOfYear()->toDateString(),
            'data_fim' => now()->endOfYear()->toDateString(),
        ]);
    }

    public function test_filtra_turmas_por_periodo_letivo(): void
    {
        $anterior = Turma::factory()->create(['periodo_letivo_id' => $this->periodoAnterior->id]);
        $vigente = Turma::factory()->create(['periodo_letivo_id' => $this->periodoVigente->id]);

        Livewire::test(ListTurmas::class)
            ->assertCanSeeTableRecords([$anterior, $vigente])
            ->filterTable('periodo_letivo_id', [$this->periodoVigente->id])
            ->assertCanSeeTableRecords([$vigente])
            ->assertCanNotSeeTableRecords([$anterior])
            ->filterTable('periodo_letivo_id', [$this->periodoAnterior->id, $this->periodoVigente->id])
            ->assertCanSeeTableRecords([$anterior, $vigente]);
    }

    public function test_filtro_de_periodo_combina_com_o_de_status(): void
    {
        $ativaVigente = Turma::factory()->create(['periodo_letivo_id' => $this->periodoVigente->id]);
        $planejadaVigente = Turma::factory()->planejada()->create(['periodo_letivo_id' => $this->periodoVigente->id]);
        $ativaAnterior = Turma::factory()->create(['periodo_letivo_id' => $this->periodoAnterior->id]);

        Livewire::test(ListTurmas::class)
            ->filterTable('periodo_letivo_id', [$this->periodoVigente->id])
            ->filterTable('status', [StatusTurma::Ativa->value])
            ->assertCanSeeTableRecords([$ativaVigente])
            ->assertCanNotSeeTableRecords([$planejadaVigente, $ativaAnterior]);
    }

    public function test_ocupacao_conta_apenas_matriculas_ativas(): void
    {
        $turma = Turma::factory()->create([
            'periodo_letivo_id' => $this->periodoVigente->id,
            'vagas_maximas' => 30,
        ]);
        Matricula::factory()->count(2)->create(['turma_id' => $turma->id, 'situacao' => 'ativa']);
        Matricula::factory()->create(['turma_id' => $turma->id, 'situacao' => 'cancelada']);

        Livewire::test(ListTurmas::class)
            ->assertTableColumnStateSet('ocupacao', '2 / 30', $turma);
    }

    public function test_ocupacao_sem_limite_de_vagas_mostra_apenas_o_total(): void
    {
        $turma = Turma::factory()->create([
            'periodo_letivo_id' => $this->periodoVigente->id,
            'vagas_maximas' => null,
        ]);
        Matricula::factory()->create(['turma_id' => $turma->id, 'situacao' => 'ativa']);

        Livewire::test(ListTurmas::class)
            ->assertTableColumnStateSet('ocupacao', '1', $turma);
    }

    public function test_ordena_turmas_pela_ocupacao(): void
    {
        $vazia = Turma::factory()->create(['periodo_letivo_id' => $this->periodoVigente->id]);
        $cheia = Turma::factory()->create(['periodo_letivo_id' => $this->periodoVigente->id]);
        Matricula::factory()->count(2)->create(['turma_id' => $cheia->id, 'situacao' => 'ativa']);

        Livewire::test(ListTurmas::class)
            ->sortTable('ocupacao', 'desc')
            ->assertCanSeeTableRecords([$cheia, $vazia], inOrder: true)
            ->sortTable('ocupacao', 'asc')
            ->assertCanSeeTableRecords([$vazia, $cheia], inOrder: true);
    }

    public function test_listagem_agrupa_por_periodo_letivo_sem_erro(): void
    {
        Turma::factory()->create(['periodo_letivo_id' => $this->periodoVigente->id]);
        Turma::factory()->create(['periodo_letivo_id' => $this->periodoAnterior->id]);

        Livewire::test(ListTurmas::class)
            ->set('tableGrouping', 'periodoLetivo.nome')
            ->assertSuccessful()
            ->assertSee('Ano Vigente')
            ->assertSee('Ano Anterior');
    }
}
