<?php

namespace Tests\Feature;

use App\Enums\StatusRetencaoTarefa;
use App\Filament\Resources\RetencaoTarefas\Pages\EditRetencaoTarefa;
use App\Filament\Resources\RetencaoTarefas\Pages\ListRetencaoTarefas;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\RetencaoTarefa;
use App\Models\Turma;
use App\Models\User;
use Database\Factories\PeriodoLetivoFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RetencaoTarefaFilamentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $usuarioSemPermissao;

    protected function setUp(): void
    {
        parent::setUp();

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
    }

    private function criarTarefaRetencao(int $score = 85, string $prioridade = 'critica'): RetencaoTarefa
    {
        $aluno = Pessoa::create([
            'nome' => 'Aluno Em Risco Teste',
            'cpf' => (string) rand(10000000000, 99999999999),
            'data_nascimento' => '2010-06-15',
        ]);

        $turma = Turma::factory()->create([
            'periodo_letivo_id' => PeriodoLetivoFactory::idPadrao(),
        ]);

        $matricula = Matricula::create([
            'pessoa_id' => $aluno->id,
            'turma_id' => $turma->id,
            'situacao' => 'ativa',
        ]);

        return RetencaoTarefa::create([
            'matricula_id' => $matricula->id,
            'aluno_pessoa_id' => $aluno->id,
            'score_risco' => $score,
            'status' => StatusRetencaoTarefa::Pendente,
            'prioridade' => $prioridade,
            'titulo' => 'Alerta de Evasão - '.$aluno->nome,
            'descricao' => 'Frequência abaixo de 75% e 2 mensalidades em atraso.',
            'responsavel_user_id' => $this->admin->id,
            'data_limite' => now()->addDays(7)->toDateString(),
        ]);
    }

    public function test_usuario_sem_permissao_recebe_403_ao_acessar_playbook_de_retencao(): void
    {
        $this->actingAs($this->usuarioSemPermissao);
        session(['active_role' => 'sem_acesso']);

        $this->get('/admin/retencao-tarefas')
            ->assertStatus(403);
    }

    public function test_admin_visualiza_listagem_de_tarefas_de_retencao(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        $tarefa = $this->criarTarefaRetencao(90, 'critica');

        Livewire::test(ListRetencaoTarefas::class)
            ->assertCanSeeTableRecords([$tarefa])
            ->assertSee('Aluno Em Risco Teste')
            ->assertSee('90%');
    }

    public function test_admin_pode_concluir_acompanhamento_com_observacao(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        $tarefa = $this->criarTarefaRetencao(75, 'alta');

        Livewire::test(ListRetencaoTarefas::class)
            ->callTableAction('concluir', $tarefa, [
                'observacoes_resolucao' => 'Conversado com os responsáveis, acordado plano pedagógico e recuperação financeira.',
            ])
            ->assertHasNoTableActionErrors();

        $tarefa->refresh();

        $this->assertEquals(StatusRetencaoTarefa::Concluido, $tarefa->status);
        $this->assertNotNull($tarefa->resolvido_em);
        $this->assertStringContainsString('Conversado com os responsáveis', $tarefa->observacoes_resolucao);
    }

    public function test_admin_pode_editar_prioridade_e_prazo_da_tarefa(): void
    {
        $this->actingAs($this->admin);
        session(['active_role' => 'super_admin']);

        $tarefa = $this->criarTarefaRetencao(80, 'alta');

        Livewire::test(EditRetencaoTarefa::class, ['record' => $tarefa->id])
            ->fillForm([
                'prioridade' => 'critica',
                'status' => StatusRetencaoTarefa::EmAndamento->value,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $tarefa->refresh();

        $this->assertEquals('critica', $tarefa->prioridade);
        $this->assertEquals(StatusRetencaoTarefa::EmAndamento, $tarefa->status);
    }
}
