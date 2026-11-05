<?php

namespace Tests\Feature;

use App\Filament\Resources\Pessoas\Pages\EditPessoa;
use App\Filament\Resources\Pessoas\RelationManagers\MatriculasRelationManager as MatriculasDaPessoa;
use App\Filament\Resources\Turmas\Pages\EditTurma;
use App\Filament\Resources\Turmas\RelationManagers\MatriculasRelationManager as MatriculasDaTurma;
use App\Models\Contrato;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Turma;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A regra da exclusão protegida (ver MatriculaExclusaoProtegidaTest) vale em todas as telas que excluem uma matrícula
 * ou uma pessoa: a edição da matrícula, a relação de matrículas na pessoa e na turma e a edição da pessoa.
 */
class ExclusaoMatriculaOutrasTelasTest extends TestCase
{
    use RefreshDatabase;

    private Turma $turma;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'aluno', 'guard_name' => 'web']);
        $admin = User::factory()->create(['activated_at' => now()->subDay(), 'email_verified_at' => now()]);
        $admin->assignRole('super_admin');
        session(['active_role' => 'super_admin']);
        $this->actingAs($admin);

        $this->turma = Turma::factory()->create(['periodo_letivo_id' => PeriodoLetivo::factory()->create()->id]);
    }

    private function matricula(?Pessoa $pessoa = null): Matricula
    {
        return Matricula::factory()->create([
            'pessoa_id' => ($pessoa ?? Pessoa::factory()->create())->id,
            'turma_id' => $this->turma->id,
            'situacao' => 'ativa',
        ]);
    }

    private function comContrato(Matricula $matricula): Matricula
    {
        Contrato::create(['matricula_id' => $matricula->id, 'valor_total' => 6000.00, 'data_aceite' => '2026-01-10']);

        return $matricula;
    }

    public function test_relacao_da_pessoa_mantem_matricula_com_historico_e_avisa(): void
    {
        $pessoa = Pessoa::factory()->create();
        $matricula = $this->comContrato($this->matricula($pessoa));

        Livewire::test(MatriculasDaPessoa::class, ['ownerRecord' => $pessoa, 'pageClass' => EditPessoa::class])
            ->callTableAction('delete', $matricula)
            ->assertNotified('Matrícula não pode ser excluída');

        $this->assertDatabaseHas('matricula', ['id' => $matricula->id]);
        $this->assertDatabaseHas('contrato', ['matricula_id' => $matricula->id]);
    }

    public function test_relacao_da_pessoa_exclui_matricula_sem_historico(): void
    {
        $pessoa = Pessoa::factory()->create();
        $matricula = $this->matricula($pessoa);

        Livewire::test(MatriculasDaPessoa::class, ['ownerRecord' => $pessoa, 'pageClass' => EditPessoa::class])
            ->callTableAction('delete', $matricula);

        $this->assertDatabaseMissing('matricula', ['id' => $matricula->id]);
    }

    public function test_relacao_da_turma_exclui_em_lote_so_as_matriculas_sem_historico(): void
    {
        $livre = $this->matricula();
        $comHistorico = $this->comContrato($this->matricula());

        Livewire::test(MatriculasDaTurma::class, ['ownerRecord' => $this->turma, 'pageClass' => EditTurma::class])
            ->callTableBulkAction('delete', [$livre, $comHistorico]);

        $this->assertDatabaseMissing('matricula', ['id' => $livre->id]);
        $this->assertDatabaseHas('matricula', ['id' => $comHistorico->id]);
    }

    public function test_edicao_da_pessoa_nao_exclui_quem_tem_matricula(): void
    {
        $pessoa = Pessoa::factory()->create();
        $matricula = $this->comContrato($this->matricula($pessoa));

        Livewire::test(EditPessoa::class, ['record' => $pessoa->getRouteKey()])
            ->callAction(DeleteAction::class)
            ->assertNotified('Não é possível excluir esta pessoa');

        $this->assertDatabaseHas('pessoa', ['id' => $pessoa->id]);
        $this->assertDatabaseHas('matricula', ['id' => $matricula->id]);
        $this->assertDatabaseHas('contrato', ['matricula_id' => $matricula->id]);
    }

    public function test_edicao_da_pessoa_exclui_cadastro_sem_vinculos(): void
    {
        $pessoa = Pessoa::factory()->create();

        Livewire::test(EditPessoa::class, ['record' => $pessoa->getRouteKey()])
            ->callAction(DeleteAction::class);

        $this->assertDatabaseMissing('pessoa', ['id' => $pessoa->id]);
    }
}
