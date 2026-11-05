<?php

namespace Tests\Feature;

use App\Filament\Resources\CronogramaAulas\Pages\ListCronogramaAulas;
use App\Models\CronogramaAula;
use App\Models\PeriodoLetivo;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * O cronograma de aulas não guarda período letivo: ele vem da turma (a coluna saiu da tabela em abril/2026). Tudo que
 * mostra ou filtra o período da aula precisa passar pela turma.
 */
class CronogramaAulaPeriodoLetivoTest extends TestCase
{
    use RefreshDatabase;

    private PeriodoLetivo $periodoA;

    private PeriodoLetivo $periodoB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->periodoA = PeriodoLetivo::factory()->create(['nome' => 'Período A']);
        $this->periodoB = PeriodoLetivo::factory()->create(['nome' => 'Período B']);
    }

    private function aulaNoPeriodo(PeriodoLetivo $periodo): CronogramaAula
    {
        return CronogramaAula::factory()->create([
            'turma_id' => Turma::factory()->create(['periodo_letivo_id' => $periodo->id])->id,
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $user->assignRole(Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']));
        session(['active_role' => 'super_admin']);

        return $user;
    }

    public function test_o_periodo_da_aula_e_o_da_turma(): void
    {
        $aula = $this->aulaNoPeriodo($this->periodoB);

        $this->assertSame($this->periodoB->id, $aula->periodoLetivo?->id);
        $this->assertSame('Período B', $aula->periodoLetivo->nome);
    }

    public function test_o_periodo_pode_ser_carregado_junto_com_a_listagem(): void
    {
        $this->aulaNoPeriodo($this->periodoA);
        $this->aulaNoPeriodo($this->periodoB);

        $nomes = CronogramaAula::with('periodoLetivo')->get()->map(fn (CronogramaAula $a) => $a->periodoLetivo?->nome)->sort()->values()->all();

        $this->assertSame(['Período A', 'Período B'], $nomes);
    }

    public function test_filtro_de_periodo_da_listagem_mostra_so_as_aulas_do_periodo(): void
    {
        $doA = $this->aulaNoPeriodo($this->periodoA);
        $doB = $this->aulaNoPeriodo($this->periodoB);

        Livewire::actingAs($this->admin())
            ->test(ListCronogramaAulas::class)
            ->removeTableFilter('frequencias_pendentes')
            ->filterTable('periodo_letivo_id', $this->periodoA->id)
            ->assertCanSeeTableRecords([$doA])
            ->assertCanNotSeeTableRecords([$doB]);
    }

    public function test_editar_e_clonar_em_lote_nao_oferecem_um_periodo_que_nao_grava(): void
    {
        $aula = $this->aulaNoPeriodo($this->periodoA);

        Livewire::actingAs($this->admin())
            ->test(ListCronogramaAulas::class)
            ->removeTableFilter('frequencias_pendentes')
            ->mountTableBulkAction('bulkEdit', [$aula])
            ->assertSchemaComponentDoesNotExist('periodo_letivo_id', 'mountedActionSchema0')
            ->assertSchemaComponentExists('turma_id', 'mountedActionSchema0');
    }
}
