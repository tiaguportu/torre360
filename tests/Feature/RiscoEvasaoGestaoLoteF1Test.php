<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\RetencaoTarefa;
use App\Models\Turma;
use App\Models\User;
use App\Services\RiscoEvasaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RiscoEvasaoGestaoLoteF1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (method_exists(RiscoEvasaoService::class, 'resetarExecucao')) {
            RiscoEvasaoService::resetarExecucao();
        }
    }

    private function criarMatricula(string $nomeAluno = 'Aluno Teste'): Matricula
    {
        $pessoa = Pessoa::factory()->create(['nome' => $nomeAluno]);
        $turma = Turma::factory()->create(['nome' => '3o Ano B']);

        return Matricula::factory()->create([
            'pessoa_id' => $pessoa->id,
            'turma_id' => $turma->id,
            'risco_evasao_score' => 80,
        ]);
    }

    public function test_sem_papeis_de_gestao_ninguem_e_notificado_e_tarefa_continua_criada(): void
    {
        Log::spy();

        $comum1 = User::factory()->create(['name' => 'Professor A', 'activated_at' => now()]);
        $comum2 = User::factory()->create(['name' => 'Secretaria B', 'activated_at' => now()]);

        $matricula = $this->criarMatricula('Carlos Eduardo');

        $tarefa = RiscoEvasaoService::processarPlaybook($matricula, 85);

        $this->assertInstanceOf(RetencaoTarefa::class, $tarefa);
        $this->assertEquals(0, DatabaseNotification::count());

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message) {
                return ! str_contains($message, 'Carlos Eduardo');
            });
    }

    public function test_coordenador_ativo_recebe_alerta(): void
    {
        Role::firstOrCreate(['name' => 'coordenador', 'guard_name' => 'web']);
        $coord = User::factory()->create(['name' => 'Coord Ativo', 'activated_at' => now()]);
        $coord->assignRole('coordenador');

        $matricula = $this->criarMatricula('Maria Clara');

        RiscoEvasaoService::processarPlaybook($matricula, 85);

        $this->assertEquals(1, DatabaseNotification::where('notifiable_id', $coord->id)->count());
    }

    public function test_coordenador_inativo_nao_recebe_alerta(): void
    {
        Role::firstOrCreate(['name' => 'coordenador', 'guard_name' => 'web']);
        $coordInativo = User::factory()->create([
            'name' => 'Coord Inativo',
            'activated_at' => now()->subYear(),
            'deactivated_at' => now()->subDay(),
        ]);
        $coordInativo->assignRole('coordenador');

        $matricula = $this->criarMatricula('Joao Pedro');

        RiscoEvasaoService::processarPlaybook($matricula, 85);

        $this->assertEquals(0, DatabaseNotification::where('notifiable_id', $coordInativo->id)->count());
    }

    public function test_usuario_sem_papel_de_gestao_nunca_recebe_alerta(): void
    {
        Role::firstOrCreate(['name' => 'professor', 'guard_name' => 'web']);
        $prof = User::factory()->create(['name' => 'Prof Ativo', 'activated_at' => now()]);
        $prof->assignRole('professor');

        $matricula = $this->criarMatricula('Lucas Gabriel');

        RiscoEvasaoService::processarPlaybook($matricula, 85);

        $this->assertEquals(0, DatabaseNotification::where('notifiable_id', $prof->id)->count());
    }

    public function test_acima_do_limite_sai_uma_notificacao_resumo(): void
    {
        Role::firstOrCreate(['name' => 'coordenador', 'guard_name' => 'web']);
        $coord = User::factory()->create(['name' => 'Coord Gestao', 'activated_at' => now()]);
        $coord->assignRole('coordenador');

        config(['risco_evasao.playbook.max_notificacoes_individuais' => 5]);

        RiscoEvasaoService::iniciarExecucao();
        for ($i = 1; $i <= 6; $i++) {
            $m = $this->criarMatricula("Aluno {$i}");
            RiscoEvasaoService::notificarCoordenacao($m, 80);
        }
        RiscoEvasaoService::finalizarExecucao();

        $notificacoes = DatabaseNotification::where('notifiable_id', $coord->id)->get();
        $this->assertCount(1, $notificacoes);

        $notificacao = $notificacoes->first();
        $dados = $notificacao->data;
        $conteudo = json_encode($dados);

        $this->assertStringContainsString('6', $conteudo);
        $this->assertStringContainsString('retencao-tarefas', $conteudo);
    }
}
