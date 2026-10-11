<?php

namespace Tests\Feature;

use App\Enums\TipoRelatorioPreceptoria;
use App\Filament\Resources\RelatorioPreceptorias\RelatorioPreceptoriaResource;
use App\Models\CicloPreceptoria;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\Pessoa;
use App\Models\Preceptoria;
use App\Models\RelatorioPreceptoria;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RelatorioPreceptoriaSegurancaTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $userProfA;

    private User $userProfB;

    private User $userResp;

    private Pessoa $pessoaProfA;

    private Pessoa $pessoaProfB;

    private Pessoa $pessoaResp;

    private Pessoa $alunoA;

    private Pessoa $alunoB;

    private RelatorioPreceptoria $relatorioProfAPrivado;

    private RelatorioPreceptoria $relatorioProfAPublico;

    private RelatorioPreceptoria $relatorioProfBPublico;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'professor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'responsavel', 'guard_name' => 'web']);

        $this->superAdmin = User::factory()->create([
            'activated_at' => now(),
            'email_verified_at' => now(),
        ]);
        $this->superAdmin->assignRole('super_admin');

        // Professor A
        $this->pessoaProfA = Pessoa::factory()->create(['nome' => 'Professor A']);
        $this->userProfA = User::factory()->create(['activated_at' => now(), 'email_verified_at' => now()]);
        $this->userProfA->assignRole('professor');
        $this->pessoaProfA->users()->attach($this->userProfA->id);

        // Professor B
        $this->pessoaProfB = Pessoa::factory()->create(['nome' => 'Professor B']);
        $this->userProfB = User::factory()->create(['activated_at' => now(), 'email_verified_at' => now()]);
        $this->userProfB->assignRole('professor');
        $this->pessoaProfB->users()->attach($this->userProfB->id);

        // Responsável e Alunos
        $this->pessoaResp = Pessoa::factory()->create(['nome' => 'Responsavel Aluno A']);
        $this->userResp = User::factory()->create(['activated_at' => now(), 'email_verified_at' => now()]);
        $this->userResp->assignRole('responsavel');
        $this->pessoaResp->users()->attach($this->userResp->id);

        $this->alunoA = Pessoa::factory()->create(['nome' => 'Aluno A']);
        $this->alunoA->responsaveis()->attach($this->pessoaResp->id);

        $this->alunoB = Pessoa::factory()->create(['nome' => 'Aluno B (Outra Familia)']);

        $periodo = PeriodoLetivo::factory()->create();
        $turma = Turma::factory()->create(['periodo_letivo_id' => $periodo->id]);

        $matriculaA = Matricula::factory()->create([
            'pessoa_id' => $this->alunoA->id,
            'turma_id' => $turma->id,
            'situacao' => 'ativa',
        ]);

        $matriculaB = Matricula::factory()->create([
            'pessoa_id' => $this->alunoB->id,
            'turma_id' => $turma->id,
            'situacao' => 'ativa',
        ]);

        $ciclo = CicloPreceptoria::create([
            'periodo_letivo_id' => $periodo->id,
            'nome' => 'Ciclo 1',
            'data_inicio' => now()->startOfYear(),
            'data_fim' => now()->endOfYear(),
            'ativo' => true,
        ]);

        $preceptoriaA = Preceptoria::factory()->create([
            'ciclo_preceptoria_id' => $ciclo->id,
            'professor_id' => $this->pessoaProfA->id,
            'matricula_id' => $matriculaA->id,
            'data' => now()->toDateString(),
            'hora_inicio' => '14:00:00',
            'hora_fim' => '14:30:00',
        ]);

        $preceptoriaB = Preceptoria::factory()->create([
            'ciclo_preceptoria_id' => $ciclo->id,
            'professor_id' => $this->pessoaProfB->id,
            'matricula_id' => $matriculaB->id,
            'data' => now()->toDateString(),
            'hora_inicio' => '15:00:00',
            'hora_fim' => '15:30:00',
        ]);

        $this->relatorioProfAPrivado = RelatorioPreceptoria::create([
            'preceptoria_id' => $preceptoriaA->id,
            'tipo' => TipoRelatorioPreceptoria::AnaliseGeral,
            'corpo' => 'Observação confidencial do professor A',
            'publico' => false,
        ]);

        $this->relatorioProfAPublico = RelatorioPreceptoria::create([
            'preceptoria_id' => $preceptoriaA->id,
            'tipo' => TipoRelatorioPreceptoria::PlanoPessoalMelhora,
            'corpo' => 'Orientações pedagógicas para os pais do Aluno A',
            'publico' => true,
        ]);

        $this->relatorioProfBPublico = RelatorioPreceptoria::create([
            'preceptoria_id' => $preceptoriaB->id,
            'tipo' => TipoRelatorioPreceptoria::AcompanhamentoAcademico,
            'corpo' => 'Relatório do Aluno B feito pelo Prof B',
            'publico' => true,
        ]);
    }

    public function test_super_admin_enxerga_todos_os_relatorios(): void
    {
        $this->actingAs($this->superAdmin);
        session(['active_role' => 'super_admin']);

        $records = RelatorioPreceptoriaResource::getEloquentQuery()->pluck('id');

        $this->assertCount(3, $records);
        $this->assertTrue($records->contains($this->relatorioProfAPrivado->id));
        $this->assertTrue($records->contains($this->relatorioProfAPublico->id));
        $this->assertTrue($records->contains($this->relatorioProfBPublico->id));
    }

    public function test_professor_so_enxerga_relatorios_das_suas_proprias_preceptorias(): void
    {
        $this->actingAs($this->userProfA);
        session(['active_role' => 'professor']);

        $records = RelatorioPreceptoriaResource::getEloquentQuery()->pluck('id');

        $this->assertCount(2, $records);
        $this->assertTrue($records->contains($this->relatorioProfAPrivado->id));
        $this->assertTrue($records->contains($this->relatorioProfAPublico->id));
        $this->assertFalse($records->contains($this->relatorioProfBPublico->id));
    }

    public function test_responsavel_so_enxerga_relatorios_publicos_do_seu_aluno(): void
    {
        $this->actingAs($this->userResp);
        session(['active_role' => 'responsavel']);

        $records = RelatorioPreceptoriaResource::getEloquentQuery()->pluck('id');

        $this->assertCount(1, $records);
        $this->assertTrue($records->contains($this->relatorioProfAPublico->id));
        $this->assertFalse($records->contains($this->relatorioProfAPrivado->id), 'Relatório confidencial/privado não pode ser visível aos pais.');
        $this->assertFalse($records->contains($this->relatorioProfBPublico->id), 'Relatório de outro aluno não pode ser visível a outra família.');
    }

    public function test_usuario_sem_pessoa_vinculada_ou_deslogado_recebe_query_vazia(): void
    {
        $userSemPessoa = User::factory()->create([
            'activated_at' => now(),
            'email_verified_at' => now(),
        ]);
        $userSemPessoa->assignRole('professor');

        $this->actingAs($userSemPessoa);

        $records = RelatorioPreceptoriaResource::getEloquentQuery()->get();
        $this->assertEmpty($records);
    }
}
