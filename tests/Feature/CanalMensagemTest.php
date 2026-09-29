<?php

namespace Tests\Feature;

use App\Mail\MensagemGenericaMail;
use App\Models\Pessoa;
use App\Models\User;
use App\Services\Canais\EmailCanal;
use App\Services\Canais\FcmCanal;
use App\Services\CanalMensagemManager;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class CanalMensagemTest extends TestCase
{
    use RefreshDatabase;

    // ─── EmailCanal ─────────────────────────────────────────────────

    public function test_email_canal_envia_e_registra_no_log(): void
    {
        Mail::fake();

        $pessoa = Pessoa::factory()->create(['email' => 'familia@example.com']);

        $enviou = app(EmailCanal::class)->enviar($pessoa, 'Assunto Teste', '<p>Corpo</p>');

        $this->assertTrue($enviou);
        Mail::assertQueued(MensagemGenericaMail::class, fn (MensagemGenericaMail $mail) => $mail->assunto === 'Assunto Teste');
        $this->assertDatabaseHas('email_logs', ['subject' => 'Assunto Teste']);
    }

    public function test_email_canal_nao_esta_disponivel_sem_email(): void
    {
        Mail::fake();

        $pessoa = Pessoa::factory()->create(['email' => null]);
        $canal = app(EmailCanal::class);

        $this->assertFalse($canal->disponivelPara($pessoa));
        $this->assertFalse($canal->enviar($pessoa, 'Assunto', 'Corpo'));
        Mail::assertNothingQueued();
    }

    // ─── FcmCanal ───────────────────────────────────────────────────

    private function mockFcmService(bool $sucesso = true): void
    {
        $mock = Mockery::mock(FcmService::class);
        $mock->shouldReceive('sendPush')->andReturn(['success' => $sucesso]);
        $this->app->instance(FcmService::class, $mock);
    }

    public function test_fcm_canal_envia_para_usuarios_da_pessoa_com_token(): void
    {
        $this->mockFcmService();

        $pessoa = Pessoa::factory()->create();
        $user = User::factory()->create(['fcm_token' => 'token-abc']);
        $pessoa->users()->attach($user->id);

        $canal = app(FcmCanal::class);

        $this->assertTrue($canal->disponivelPara($pessoa));
        $this->assertTrue($canal->enviar($pessoa, 'Título', '<p>Mensagem</p>'));
    }

    public function test_fcm_canal_indisponivel_sem_usuario_com_token(): void
    {
        $this->mockFcmService();

        $pessoa = Pessoa::factory()->create();
        $user = User::factory()->create(['fcm_token' => null]);
        $pessoa->users()->attach($user->id);

        $canal = app(FcmCanal::class);

        $this->assertFalse($canal->disponivelPara($pessoa));
        $this->assertFalse($canal->enviar($pessoa, 'Título', 'Mensagem'));
    }

    // ─── CanalMensagemManager ───────────────────────────────────────

    public function test_manager_resolve_canais_conhecidos_e_lista_opcoes(): void
    {
        $this->mockFcmService();

        $this->assertInstanceOf(EmailCanal::class, CanalMensagemManager::resolver('email'));
        $this->assertInstanceOf(FcmCanal::class, CanalMensagemManager::resolver('fcm'));
        $this->assertSame(['email' => 'E-mail', 'fcm' => 'Notificação push (app)'], CanalMensagemManager::opcoes());
    }

    public function test_manager_lanca_excecao_para_canal_desconhecido(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CanalMensagemManager::resolver('whatsapp');
    }
}
