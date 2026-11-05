<?php

namespace Tests\Feature\Preceptorias;

use App\Filament\Resources\Matriculas\Pages\EditMatricula;
use App\Filament\Resources\Preceptorias\Pages\AgendarPreceptoria;
use App\Models\CronogramaAula;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Preceptoria;
use App\Models\Turma;
use App\Models\User;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Na tela de agendar, o aluno só enxerga horários do professor conselheiro da turma e dos professores
 * designados para a matrícula dele; quem apenas dá aula para a turma não aparece.
 */
class AgendarPreceptoriaProfessoresDaMatriculaTest extends TestCase
{
    use RefreshDatabase;

    private function usuarioCom(array $permissoes, string $papelAtivo = 'admin'): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $papel = Role::firstOrCreate(['name' => $papelAtivo, 'guard_name' => 'web']);
        foreach ($permissoes as $nome) {
            $papel->givePermissionTo(Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']));
        }

        $user = User::factory()->create();
        $user->assignRole($papel);
        session(['active_role' => $papelAtivo]);

        return $user;
    }

    private function horarioLivre(Pessoa $professor, string $data): Preceptoria
    {
        // o horário só é oferecido se o ciclo for do período letivo da matrícula (a turma da fábrica usa o período padrão)
        return Preceptoria::factory()->comCicloValido()->create(['professor_id' => $professor->id, 'matricula_id' => null, 'data' => $data]);
    }

    private function matriculaComTurmaDe(Pessoa $conselheiro): Matricula
    {
        $turma = Turma::factory()->create(['professor_conselheiro_id' => $conselheiro->id]);

        return Matricula::factory()->create(['pessoa_id' => Pessoa::factory()->create()->id, 'turma_id' => $turma->id]);
    }

    /**
     * @return array<int|string, string>
     */
    private function horariosOferecidos($pagina): array
    {
        $oferecidos = [];

        $pagina->assertFormComponentExists('preceptoria_id', 'form', function (Select $campo) use (&$oferecidos): bool {
            $oferecidos = $campo->getOptions();

            return true;
        });

        return $oferecidos;
    }

    public function test_oferece_so_horarios_do_conselheiro_e_do_designado_na_matricula(): void
    {
        $user = $this->usuarioCom(['ViewAny:Preceptoria', 'Agendar:Preceptoria']);
        $conselheiro = Pessoa::factory()->create();
        $designado = Pessoa::factory()->create();
        $doCronograma = Pessoa::factory()->create();
        $estranho = Pessoa::factory()->create();
        $matricula = $this->matriculaComTurmaDe($conselheiro);
        $matricula->professoresDePreceptoria()->attach($designado->id);
        CronogramaAula::factory()->create(['turma_id' => $matricula->turma_id, 'pessoa_id' => $doCronograma->id]);

        $doConselheiro = $this->horarioLivre($conselheiro, now()->addDays(3)->toDateString());
        $doDesignado = $this->horarioLivre($designado, now()->addDays(4)->toDateString());
        $doProfessorDeAula = $this->horarioLivre($doCronograma, now()->addDays(5)->toDateString());
        $doEstranho = $this->horarioLivre($estranho, now()->addDays(6)->toDateString());

        $pagina = Livewire::actingAs($user)
            ->test(AgendarPreceptoria::class)
            ->fillForm(['matricula_id' => $matricula->id]);

        $oferecidos = array_keys($this->horariosOferecidos($pagina));

        $this->assertEqualsCanonicalizing([$doConselheiro->id, $doDesignado->id], $oferecidos);
        $this->assertNotContains($doProfessorDeAula->id, $oferecidos);
        $this->assertNotContains($doEstranho->id, $oferecidos);
    }

    public function test_designado_de_uma_matricula_nao_vale_para_outra_do_mesmo_aluno_nem_da_turma(): void
    {
        $user = $this->usuarioCom(['ViewAny:Preceptoria', 'Agendar:Preceptoria']);
        $conselheiro = Pessoa::factory()->create();
        $designado = Pessoa::factory()->create();
        $matricula = $this->matriculaComTurmaDe($conselheiro);
        $colega = Matricula::factory()->create(['pessoa_id' => Pessoa::factory()->create()->id, 'turma_id' => $matricula->turma_id]);
        $matricula->professoresDePreceptoria()->attach($designado->id);
        $horario = $this->horarioLivre($designado, now()->addDays(3)->toDateString());

        $pagina = Livewire::actingAs($user)
            ->test(AgendarPreceptoria::class)
            ->fillForm(['matricula_id' => $colega->id]);

        $this->assertNotContains($horario->id, array_keys($this->horariosOferecidos($pagina)));
    }

    public function test_agendar_com_horario_de_professor_que_nao_e_da_matricula_e_recusado(): void
    {
        Notification::fake();

        $user = $this->usuarioCom(['ViewAny:Preceptoria', 'Agendar:Preceptoria']);
        $matricula = $this->matriculaComTurmaDe(Pessoa::factory()->create());
        $professorDeAula = Pessoa::factory()->create();
        CronogramaAula::factory()->create(['turma_id' => $matricula->turma_id, 'pessoa_id' => $professorDeAula->id]);
        $horario = $this->horarioLivre($professorDeAula, now()->addDays(3)->toDateString());

        Livewire::actingAs($user)
            ->test(AgendarPreceptoria::class)
            ->fillForm(['matricula_id' => $matricula->id, 'preceptoria_id' => $horario->id])
            ->call('agendar');

        $this->assertNull($horario->fresh()->matricula_id);
    }

    public function test_agendar_com_horario_do_designado_funciona(): void
    {
        Notification::fake();

        $user = $this->usuarioCom(['ViewAny:Preceptoria', 'Agendar:Preceptoria']);
        $designado = Pessoa::factory()->create();
        $matricula = $this->matriculaComTurmaDe(Pessoa::factory()->create());
        $matricula->professoresDePreceptoria()->attach($designado->id);
        $horario = $this->horarioLivre($designado, now()->addDays(3)->toDateString());

        Livewire::actingAs($user)
            ->test(AgendarPreceptoria::class)
            ->fillForm(['matricula_id' => $matricula->id, 'preceptoria_id' => $horario->id])
            ->call('agendar');

        $this->assertSame($matricula->id, $horario->fresh()->matricula_id);
    }

    public function test_matricula_da_url_vem_selecionada_quando_o_usuario_pode_agendar(): void
    {
        $user = $this->usuarioCom(['ViewAny:Preceptoria', 'Agendar:Preceptoria']);
        $matricula = $this->matriculaComTurmaDe(Pessoa::factory()->create());

        Livewire::withQueryParams(['matricula_id' => $matricula->id])
            ->actingAs($user)
            ->test(AgendarPreceptoria::class)
            ->assertSchemaStateSet(['matricula_id' => $matricula->id]);
    }

    public function test_matricula_da_url_fora_do_alcance_do_usuario_e_ignorada(): void
    {
        $responsavel = $this->usuarioCom(['ViewAny:Preceptoria', 'Agendar:Preceptoria'], 'responsavel');
        $dePessoaAlheia = $this->matriculaComTurmaDe(Pessoa::factory()->create());

        Livewire::withQueryParams(['matricula_id' => $dePessoaAlheia->id])
            ->actingAs($responsavel)
            ->test(AgendarPreceptoria::class)
            ->assertSchemaStateSet(['matricula_id' => null]);
    }

    public function test_cadastro_da_matricula_grava_os_professores_designados(): void
    {
        $user = $this->usuarioCom(['ViewAny:Matricula', 'View:Matricula', 'Update:Matricula']);
        // o campo de aluno do formulário filtra por este papel
        Role::firstOrCreate(['name' => 'aluno', 'guard_name' => 'web']);
        $designado = Pessoa::factory()->create();
        $matricula = $this->matriculaComTurmaDe(Pessoa::factory()->create());

        Livewire::actingAs($user)
            ->test(EditMatricula::class, ['record' => $matricula->getKey()])
            ->fillForm(['professoresDePreceptoria' => [$designado->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([$designado->id], $matricula->fresh()->professoresDePreceptoria->modelKeys());
    }
}
