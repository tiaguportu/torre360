<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StatusRematricula;
use App\Enums\StatusTurma;
use App\Filament\Resources\Rematriculas\Pages\ListRematriculas;
use App\Models\Curso;
use App\Models\Matricula;
use App\Models\PeriodoLetivo;
use App\Models\PeriodoRematricula;
use App\Models\Pessoa;
use App\Models\Rematricula;
use App\Models\Serie;
use App\Models\TemplateContrato;
use App\Models\Turma;
use App\Models\Turno;
use App\Models\Unidade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RematriculaFilamentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $usuarioSemPermissao;

    private PeriodoLetivo $origem;

    private PeriodoLetivo $destino;

    private Serie $serie;

    private Turno $turno;

    private Turma $turmaOrigem;

    private Turma $turmaDestino;

    private PeriodoRematricula $campanha;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config(['services.assinafy.key' => '']);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $roleSuper = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $roleSemAcesso = Role::firstOrCreate(['name' => 'sem_acesso', 'guard_name' => 'web']);

        $this->admin = User::factory()->create([
            'activated_at' => now()->subDay(),
            'email_verified_at' => now(),
        ]);
        $this->admin->assignRole($roleSuper);

        $this->usuarioSemPermissao = User::factory()->create([
            'activated_at' => now()->subDay(),
            'email_verified_at' => now(),
        ]);
        $this->usuarioSemPermissao->assignRole($roleSemAcesso);

        $this->origem = PeriodoLetivo::create(['nome' => '2026', 'data_inicio' => '2026-02-01', 'data_fim' => '2026-12-15']);
        $this->destino = PeriodoLetivo::create(['nome' => '2027', 'data_inicio' => '2027-02-01', 'data_fim' => '2027-12-15']);

        $unidade = Unidade::create(['nome' => 'Unidade Sul']);
        $curso = Curso::create(['nome_externo' => 'Ensino Fundamental', 'nome_interno' => 'EF', 'unidade_id' => $unidade->id]);
        $this->serie = Serie::create(['nome' => '7º Ano', 'curso_id' => $curso->id, 'sistema_avaliacao' => 'Nota']);
        $this->turno = Turno::create(['nome' => 'Matutino', 'hora_inicio' => '07:30:00', 'hora_fim' => '12:00:00']);

        $this->turmaOrigem = Turma::create([
            'nome' => '6º Ano A 2026',
            'serie_id' => $this->serie->id,
            'turno_id' => $this->turno->id,
            'periodo_letivo_id' => $this->origem->id,
            'status' => StatusTurma::Ativa,
        ]);

        $this->turmaDestino = Turma::create([
            'nome' => '7º Ano A 2027',
            'serie_id' => $this->serie->id,
            'turno_id' => $this->turno->id,
            'periodo_letivo_id' => $this->destino->id,
            'status' => StatusTurma::Ativa,
            'vagas_maximas' => 30,
        ]);

        $templateContrato = TemplateContrato::create([
            'nome' => 'Contrato Padrão 2027',
            'conteudo' => '<p>Contrato escolar teste.</p>',
            'is_padrao' => true,
        ]);

        $this->campanha = PeriodoRematricula::create([
            'nome' => 'Rematrícula 2027',
            'periodo_letivo_origem_id' => $this->origem->id,
            'periodo_letivo_destino_id' => $this->destino->id,
            'template_contrato_id' => $templateContrato->id,
            'data_inicio' => now()->subDays(5)->toDateString(),
            'data_fim' => now()->addDays(25)->toDateString(),
            'is_ativo' => true,
            'quantidade_parcelas_padrao' => 12,
            'valor_taxa' => 600.00,
        ]);
    }

    public function test_usuario_sem_permissao_recebe_403_ao_acessar_rematriculas(): void
    {
        $this->actingAs($this->usuarioSemPermissao);
        session(['active_role' => 'sem_acesso']);

        $this->get('/admin/rematriculas')
            ->assertStatus(403);
    }

    public function test_admin_visualiza_listagem_de_rematriculas(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        $aluno = Pessoa::create([
            'nome' => 'Clarice Lispector Silva',
            'cpf' => '98765432109',
            'data_nascimento' => '2013-12-10',
        ]);

        $matricula = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $this->turmaOrigem->id,
            'situacao' => 'ativa',
        ]);

        $rematricula = Rematricula::create([
            'matricula_origem_id' => $matricula->id,
            'periodo_rematricula_id' => $this->campanha->id,
            'serie_destino_id' => $this->serie->id,
            'turno_pretendido_id' => $this->turno->id,
            'solicitante_user_id' => $this->admin->id,
            'status' => StatusRematricula::DadosConfirmados,
            'data_confirmacao' => now(),
        ]);

        Livewire::test(ListRematriculas::class)
            ->assertCanSeeTableRecords([$rematricula])
            ->assertSee('Clarice Lispector Silva')
            ->assertSee('Rematrícula 2027');
    }

    public function test_filtro_aguardando_turma_filtra_registros_pendentes(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        $aluno1 = Pessoa::create(['nome' => 'Aluno Pendente']);
        $mat1 = Matricula::create(['pessoa_id' => $aluno1->id, 'turma_id' => $this->turmaOrigem->id, 'situacao' => 'ativa']);

        $rematriculaPendente = Rematricula::create([
            'matricula_origem_id' => $mat1->id,
            'periodo_rematricula_id' => $this->campanha->id,
            'solicitante_user_id' => $this->admin->id,
            'status' => StatusRematricula::DadosConfirmados,
            'nova_matricula_id' => null,
        ]);

        $aluno2 = Pessoa::create(['nome' => 'Aluno Concluído']);
        $mat2 = Matricula::create(['pessoa_id' => $aluno2->id, 'turma_id' => $this->turmaOrigem->id, 'situacao' => 'ativa']);
        $novaMat = Matricula::create(['pessoa_id' => $aluno2->id, 'turma_id' => $this->turmaDestino->id, 'situacao' => 'ativa']);

        $rematriculaConcluida = Rematricula::create([
            'matricula_origem_id' => $mat2->id,
            'periodo_rematricula_id' => $this->campanha->id,
            'solicitante_user_id' => $this->admin->id,
            'status' => StatusRematricula::Confirmada,
            'nova_matricula_id' => $novaMat->id,
        ]);

        Livewire::test(ListRematriculas::class)
            ->filterTable('aguardando_turma')
            ->assertCanSeeTableRecords([$rematriculaPendente])
            ->assertCanNotSeeTableRecords([$rematriculaConcluida]);
    }

    public function test_acao_efetivar_na_tabela_cria_nova_matricula(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        $aluno = Pessoa::create([
            'nome' => 'Vinicius de Moraes',
            'cpf' => '55544433322',
            'data_nascimento' => '2014-04-10',
        ]);

        $matricula = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $this->turmaOrigem->id,
            'situacao' => 'ativa',
        ]);

        $rematricula = Rematricula::create([
            'matricula_origem_id' => $matricula->id,
            'periodo_rematricula_id' => $this->campanha->id,
            'serie_destino_id' => $this->serie->id,
            'turno_pretendido_id' => $this->turno->id,
            'solicitante_user_id' => $this->admin->id,
            'status' => StatusRematricula::DadosConfirmados,
            'data_confirmacao' => now(),
        ]);

        Livewire::test(ListRematriculas::class)
            ->callTableAction('efetivar', $rematricula, data: [
                'turma_id' => $this->turmaDestino->id,
            ])
            ->assertHasNoTableActionErrors();

        $rematricula->refresh();

        $this->assertNotNull($rematricula->nova_matricula_id);
        $this->assertEquals($this->turmaDestino->id, $rematricula->turma_destino_id);

        $this->assertDatabaseHas('matricula', [
            'id' => $rematricula->nova_matricula_id,
            'pessoa_id' => $aluno->id,
            'turma_id' => $this->turmaDestino->id,
        ]);
    }
}
