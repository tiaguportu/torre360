<?php

namespace Tests\Feature;

use App\Models\Questionario;
use App\Models\QuestionarioAlvo;
use App\Models\QuestionarioResposta;
use App\Models\User;
use App\Notifications\QuestionarioDisponivelNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AvisoQuestionarioTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Testa a obtenção de e-mails de respondedores quando não há alvos configurados.
     */
    public function test_obter_emails_respondedores_sem_alvos(): void
    {
        // Cria usuários ativos
        $user1 = User::factory()->create([
            'email' => 'user1@example.com',
            'activated_at' => now()->subDays(1),
            'deactivated_at' => null,
        ]);

        $user2 = User::factory()->create([
            'email' => 'user2@example.com',
            'activated_at' => now()->subDays(1),
            'deactivated_at' => null,
        ]);

        // Usuário inativo (não deve receber)
        User::factory()->create([
            'email' => 'inativo@example.com',
            'activated_at' => null,
        ]);

        // Usuário desativado (não deve receber)
        User::factory()->create([
            'email' => 'desativado@example.com',
            'activated_at' => now()->subDays(5),
            'deactivated_at' => now()->subDays(1),
        ]);

        $questionario = Questionario::create([
            'titulo' => 'Questionário Geral',
            'is_ativo' => true,
        ]);

        $emails = $questionario->obterEmailsRespondedores();

        $this->assertCount(2, $emails);
        $this->assertContains('user1@example.com', $emails);
        $this->assertContains('user2@example.com', $emails);
        $this->assertNotContains('inativo@example.com', $emails);
    }

    /**
     * Testa a obtenção de e-mails de respondedores com alvo do tipo User.
     */
    public function test_obter_emails_respondedores_alvo_user(): void
    {
        $user1 = User::factory()->create(['email' => 'user1@example.com', 'activated_at' => now()]);
        $user2 = User::factory()->create(['email' => 'user2@example.com', 'activated_at' => now()]);

        $questionario = Questionario::create([
            'titulo' => 'Questionário Alvo User',
            'is_ativo' => true,
        ]);

        QuestionarioAlvo::create([
            'questionario_id' => $questionario->id,
            'alvo_type' => 'User',
            'alvo_id' => $user1->id,
        ]);

        $emails = $questionario->obterEmailsRespondedores();

        $this->assertCount(1, $emails);
        $this->assertContains('user1@example.com', $emails);
        $this->assertNotContains('user2@example.com', $emails);
    }

    /**
     * Testa a obtenção de e-mails excluindo quem já respondeu e atingiu o limite.
     */
    public function test_obter_emails_exclui_usuarios_que_atingiram_limite_de_respostas(): void
    {
        $user1 = User::factory()->create(['email' => 'user1@example.com', 'activated_at' => now()]);
        $user2 = User::factory()->create(['email' => 'user2@example.com', 'activated_at' => now()]);

        $questionario = Questionario::create([
            'titulo' => 'Questionário Limite 1',
            'is_ativo' => true,
            'max_respostas_por_usuario' => 1,
            'is_anonimo' => false,
        ]);

        // user1 já respondeu
        QuestionarioResposta::create([
            'questionario_id' => $questionario->id,
            'user_id' => $user1->id,
            'status' => 'enviado',
            'inicio_preenchimento' => now(),
            'fim_preenchimento' => now(),
        ]);

        $emails = $questionario->obterEmailsRespondedores();

        $this->assertCount(1, $emails);
        $this->assertContains('user2@example.com', $emails);
        $this->assertNotContains('user1@example.com', $emails);
    }

    /**
     * Testa o envio da notificação pelo fluxo da Action (faked).
     */
    public function test_envio_notificacao_aviso(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'alvo@example.com', 'activated_at' => now()]);

        $questionario = Questionario::create([
            'titulo' => 'Questionário de Teste',
            'is_ativo' => true,
        ]);

        QuestionarioAlvo::create([
            'questionario_id' => $questionario->id,
            'alvo_type' => 'User',
            'alvo_id' => $user->id,
        ]);

        $emails = $questionario->obterEmailsRespondedores();
        $this->assertContains('alvo@example.com', $emails);

        $usuarios = User::whereIn('email', $emails)->get();
        foreach ($usuarios as $u) {
            $u->notify(new QuestionarioDisponivelNotification($questionario));
        }

        Notification::assertSentTo(
            $user,
            QuestionarioDisponivelNotification::class,
            function ($notification, $channels) use ($questionario) {
                return $notification->questionario->id === $questionario->id;
            }
        );
    }
}
