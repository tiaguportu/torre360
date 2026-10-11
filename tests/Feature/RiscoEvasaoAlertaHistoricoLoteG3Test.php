<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SituacaoMatricula;
use App\Models\Matricula;
use App\Models\Pessoa;
use App\Models\Turma;
use App\Models\User;
use App\Services\RiscoEvasaoService;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RiscoEvasaoAlertaHistoricoLoteG3Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RiscoEvasaoService::resetarExecucao();
    }

    private function criarMatricula(string $nomeAluno = 'Aluno Teste', int $score = 80): Matricula
    {
        $pessoa = Pessoa::factory()->create(['nome' => $nomeAluno]);
        $turma = Turma::factory()->create(['nome' => 'Turma G3']);

        return Matricula::factory()->create([
            'pessoa_id' => $pessoa->id,
            'turma_id' => $turma->id,
            'situacao' => SituacaoMatricula::ATIVA,
            'risco_evasao_score' => $score,
        ]);
    }

    private function criarGestor(string $nome = 'Coordenador Gestor'): User
    {
        Role::firstOrCreate(['name' => 'coordenador', 'guard_name' => 'web']);
        $user = User::factory()->create(['name' => $nome, 'activated_at' => now()]);
        $user->assignRole('coordenador');

        return $user;
    }

    public function test_alerta_de_ontem_lido_continua_existindo_apos_recalculo_grande(): void
    {
        $gestor = $this->criarGestor();

        // Alerta de ontem já lido
        Notification::make()
            ->title('⚠️ Alerta de Risco de Evasão: Aluno Antigo (90%)')
            ->body('Notificação antiga')
            ->sendToDatabase($gestor);

        $notifAntiga = DatabaseNotification::where('notifiable_id', $gestor->id)->first();
        $this->assertNotNull($notifAntiga);
        $notifAntiga->markAsRead();
        $notifAntigaId = $notifAntiga->id;

        // Outra notificação com retenção
        Notification::make()
            ->title('Aviso de retenção institucional')
            ->body('Outro assunto')
            ->sendToDatabase($gestor);

        config(['risco_evasao.playbook.max_notificacoes_individuais' => 5]);

        RiscoEvasaoService::iniciarExecucao();
        for ($i = 1; $i <= 6; $i++) {
            $m = $this->criarMatricula("Aluno Novo {$i}");
            RiscoEvasaoService::notificarCoordenacao($m, 85);
        }
        RiscoEvasaoService::finalizarExecucao();

        // Notificação de ontem continua existindo intacta e lida
        $antigaRecarregada = DatabaseNotification::find($notifAntigaId);
        $this->assertNotNull($antigaRecarregada);
        $this->assertNotNull($antigaRecarregada->read_at);

        // Deve existir o resumo novo além das 2 notificações antigas
        $resumo = DatabaseNotification::where('notifiable_id', $gestor->id)
            ->where('data', 'like', '%6 alunos em risco%')
            ->first();
        $this->assertNotNull($resumo);
    }

    public function test_seis_alertas_na_execucao_gera_um_resumo_por_destinatario_e_zero_individuais(): void
    {
        $coord1 = $this->criarGestor('Coord 1');
        $coord2 = $this->criarGestor('Coord 2');

        config(['risco_evasao.playbook.max_notificacoes_individuais' => 5]);

        RiscoEvasaoService::iniciarExecucao();
        for ($i = 1; $i <= 6; $i++) {
            $m = $this->criarMatricula("Aluno {$i}");
            RiscoEvasaoService::notificarCoordenacao($m, 85);
        }
        RiscoEvasaoService::finalizarExecucao();

        // Cada gestor recebe exatamente 1 notificação (o resumo)
        $this->assertEquals(1, DatabaseNotification::where('notifiable_id', $coord1->id)->count());
        $this->assertEquals(1, DatabaseNotification::where('notifiable_id', $coord2->id)->count());

        $notif = DatabaseNotification::where('notifiable_id', $coord1->id)->first();
        $this->assertStringContainsString('6 alunos em risco', $notif->data['title']);
    }

    public function test_cinco_alertas_na_execucao_gera_cinco_individuais(): void
    {
        $coord = $this->criarGestor();

        config(['risco_evasao.playbook.max_notificacoes_individuais' => 5]);

        RiscoEvasaoService::iniciarExecucao();
        for ($i = 1; $i <= 5; $i++) {
            $m = $this->criarMatricula("Aluno {$i}");
            RiscoEvasaoService::notificarCoordenacao($m, 85);
        }
        RiscoEvasaoService::finalizarExecucao();

        // 5 individuais
        $this->assertEquals(5, DatabaseNotification::where('notifiable_id', $coord->id)->count());
    }

    public function test_chamada_isolada_envia_na_hora(): void
    {
        $coord = $this->criarGestor();

        // Sem iniciarExecucao
        $m = $this->criarMatricula('Aluno Solitário');
        RiscoEvasaoService::notificarCoordenacao($m, 90);

        $this->assertEquals(1, DatabaseNotification::where('notifiable_id', $coord->id)->count());
        $notif = DatabaseNotification::where('notifiable_id', $coord->id)->first();
        $this->assertStringContainsString('Aluno Solitário', $notif->data['title']);
    }

    public function test_excecao_no_meio_do_laco_ainda_dispara_finalizar_execucao(): void
    {
        $coord = $this->criarGestor();
        config(['risco_evasao.playbook.max_notificacoes_individuais' => 5]);

        RiscoEvasaoService::iniciarExecucao();
        try {
            for ($i = 1; $i <= 3; $i++) {
                $m = $this->criarMatricula("Aluno {$i}");
                RiscoEvasaoService::notificarCoordenacao($m, 85);
            }
            throw new \RuntimeException('Falha simulada no meio do processamento');
        } catch (\RuntimeException) {
            // capturado
        } finally {
            RiscoEvasaoService::finalizarExecucao();
        }

        // Os 3 acumulados até a falha devem ter sido enviados
        $this->assertEquals(3, DatabaseNotification::where('notifiable_id', $coord->id)->count());
    }

    public function test_duas_execucoes_seguidas_sao_independentes(): void
    {
        $coord = $this->criarGestor();
        config(['risco_evasao.playbook.max_notificacoes_individuais' => 5]);

        // Execução 1: 3 alertas
        RiscoEvasaoService::iniciarExecucao();
        for ($i = 1; $i <= 3; $i++) {
            $m = $this->criarMatricula("Lote1 Aluno {$i}");
            RiscoEvasaoService::notificarCoordenacao($m, 85);
        }
        RiscoEvasaoService::finalizarExecucao();

        $this->assertEquals(3, DatabaseNotification::where('notifiable_id', $coord->id)->count());

        // Execução 2: 2 alertas (não deve acumular 3+2=5)
        RiscoEvasaoService::iniciarExecucao();
        for ($i = 1; $i <= 2; $i++) {
            $m = $this->criarMatricula("Lote2 Aluno {$i}");
            RiscoEvasaoService::notificarCoordenacao($m, 85);
        }
        RiscoEvasaoService::finalizarExecucao();

        // 3 do primeiro lote + 2 do segundo lote = 5 individuais
        $this->assertEquals(5, DatabaseNotification::where('notifiable_id', $coord->id)->count());
    }

    public function test_comando_artisan_com_seis_matriculas_em_risco_gera_um_resumo(): void
    {
        $coord = $this->criarGestor();
        config([
            'risco_evasao.playbook.max_notificacoes_individuais' => 5,
            'risco_evasao.playbook.corte_risco' => 0,
            'risco_evasao.playbook.intervalo_dias_alerta' => 0,
            'risco_evasao.playbook.notificar_coordenacao' => true,
        ]);

        for ($i = 1; $i <= 6; $i++) {
            $this->criarMatricula("Aluno Artisan {$i}");
        }

        $this->artisan('academico:recalcular-risco-evasao')
            ->assertSuccessful();

        $this->assertEquals(1, DatabaseNotification::where('notifiable_id', $coord->id)->count());
        $notif = DatabaseNotification::where('notifiable_id', $coord->id)->first();
        $this->assertStringContainsString('6 alunos em risco', $notif->data['title']);
    }
}
