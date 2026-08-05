<?php

namespace Tests\Feature\Preceptorias;

use App\Filament\Resources\Preceptorias\Pages\ListPreceptorias;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Preceptoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PreceptoriaMatriculaFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_responsavel_ve_apenas_preceptorias_dos_seus_dependentes_e_filtra_por_matricula(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permViewAny = Permission::firstOrCreate(['name' => 'ViewAny:Preceptoria', 'guard_name' => 'web']);
        $permAgendar = Permission::firstOrCreate(['name' => 'Agendar:Preceptoria', 'guard_name' => 'web']);

        $roleResponsavel = Role::firstOrCreate(['name' => 'responsavel', 'guard_name' => 'web']);
        $roleResponsavel->givePermissionTo([$permViewAny, $permAgendar]);

        // Criar Responsavel e User
        $pessoaResponsavel = Pessoa::factory()->create(['nome' => 'Responsável Teste']);
        $userResponsavel = User::factory()->create();
        $userResponsavel->pessoas()->attach($pessoaResponsavel->id);
        $userResponsavel->assignRole($roleResponsavel);

        // Criar Aluno 1 e Matrícula 1
        $aluno1 = Pessoa::factory()->create(['nome' => 'Aluno Um']);
        $pessoaResponsavel->alunos()->attach($aluno1->id);
        $matricula1 = Matricula::factory()->create(['pessoa_id' => $aluno1->id]);

        // Criar Aluno 2 e Matrícula 2
        $aluno2 = Pessoa::factory()->create(['nome' => 'Aluno Dois']);
        $pessoaResponsavel->alunos()->attach($aluno2->id);
        $matricula2 = Matricula::factory()->create(['pessoa_id' => $aluno2->id]);

        // Criar Outro Aluno (Sem vínculo com o responsável)
        $outroAluno = Pessoa::factory()->create(['nome' => 'Outro Aluno']);
        $outraMatricula = Matricula::factory()->create(['pessoa_id' => $outroAluno->id]);

        // Preceptorias
        $preceptoriaAluno1 = Preceptoria::factory()->create(['matricula_id' => $matricula1->id]);
        $preceptoriaAluno2 = Preceptoria::factory()->create(['matricula_id' => $matricula2->id]);
        $preceptoriaOutroAluno = Preceptoria::factory()->create(['matricula_id' => $outraMatricula->id]);
        $preceptoriaSlotVago = Preceptoria::factory()->create(['matricula_id' => null]);

        session(['active_role' => 'responsavel']);

        // 1. Acesso inicial (sem filtro de matrícula): Deve ver apenas as dos seus dependentes
        Livewire::actingAs($userResponsavel)
            ->test(ListPreceptorias::class)
            ->assertCanSeeTableRecords([$preceptoriaAluno1, $preceptoriaAluno2])
            ->assertCanNotSeeTableRecords([$preceptoriaOutroAluno, $preceptoriaSlotVago]);

        // 2. Acesso com filtro de matrícula especifico (ex: matricula1->id)
        Livewire::actingAs($userResponsavel)
            ->test(ListPreceptorias::class)
            ->filterTable('matricula', $matricula1->id)
            ->assertCanSeeTableRecords([$preceptoriaAluno1])
            ->assertCanNotSeeTableRecords([$preceptoriaAluno2, $preceptoriaOutroAluno, $preceptoriaSlotVago]);
    }
}
