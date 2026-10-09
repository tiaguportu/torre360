<?php

namespace Tests\Feature;

use App\Models\EmailLog;
use App\Models\User;
use App\Notifications\UserUpdatedMail;
use App\Notifications\WelcomeUserMail;
use App\Support\EmailLogSanitizer;
use App\Support\LinkDefinirSenha;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Senhas e links de acesso não podem ficar em texto claro em e-mail, fila (`jobs`/`failed_jobs`) nem no log de e-mails.
 */
class CredenciaisEmEmailSegurasTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(): User
    {
        return User::create(['name' => 'Ana Responsável', 'email' => 'ana@teste.com', 'password' => bcrypt('senha-que-ninguem-sabe')]);
    }

    public function test_boas_vindas_leva_link_de_definicao_de_senha_e_nao_uma_senha(): void
    {
        $usuario = $this->usuario();

        $mail = (new WelcomeUserMail)->toMail($usuario);
        $texto = implode("\n", array_map('strval', array_merge($mail->introLines, $mail->outroLines)));

        $this->assertStringNotContainsStringIgnoringCase('senha:', $texto);
        $this->assertSame('Definir minha senha', $mail->actionText);
        $this->assertStringContainsString('/password-reset/reset', $mail->actionUrl);
        $this->assertStringContainsString('token=', $mail->actionUrl);
        $this->assertStringContainsString('signature=', $mail->actionUrl);
    }

    public function test_o_link_de_definicao_de_senha_funciona_com_o_broker_de_senhas(): void
    {
        $usuario = $this->usuario();

        parse_str((string) parse_url(LinkDefinirSenha::para($usuario), PHP_URL_QUERY), $consulta);

        $this->assertTrue(Password::broker()->tokenExists($usuario, $consulta['token']));
    }

    public function test_notificacao_enfileirada_nao_carrega_segredo_algum(): void
    {
        $serializada = serialize(new WelcomeUserMail);

        // O token é gerado no envio (dentro do toMail), não guardado na fila.
        $this->assertStringNotContainsString('token', $serializada);
        $this->assertStringNotContainsString('password', $serializada);
        $this->assertStringNotContainsString('password', serialize(new UserUpdatedMail(senhaAlterada: true)));
    }

    public function test_aviso_de_alteracao_so_oferece_link_quando_a_senha_mudou(): void
    {
        $usuario = $this->usuario();

        $semSenha = (new UserUpdatedMail(senhaAlterada: false))->toMail($usuario);
        $this->assertStringNotContainsString('password-reset', (string) $semSenha->actionUrl);

        $comSenha = (new UserUpdatedMail(senhaAlterada: true))->toMail($usuario);
        $this->assertStringContainsString('/password-reset/reset', $comSenha->actionUrl);
        $this->assertStringNotContainsString('nova senha é', implode(' ', array_map('strval', $comSenha->introLines)));
    }

    public function test_log_de_emails_nao_guarda_o_corpo_de_email_de_boas_vindas(): void
    {
        $usuario = $this->usuario();

        $usuario->notify(new WelcomeUserMail);

        $log = EmailLog::where('subject', 'Bem-vindo ao Torre360')->firstOrFail();

        $this->assertStringContainsString('Conteúdo omitido', $log->body);
        $this->assertStringNotContainsString('password-reset', $log->body);
        $this->assertStringNotContainsString('token=', $log->body);
    }

    public function test_sanitizador_remove_tokens_de_query_e_de_caminho_mas_mantem_o_texto(): void
    {
        $token = str_repeat('aB3', 16);
        $corpo = "<p>Olá, acesse <a href=\"https://escola.test/admissao/{$token}\">o portal</a> ou "
            .'<a href="https://escola.test/x?id=7&amp;token=abc123&amp;signature=def456">este</a>. Prazo: 10/10.</p>';

        $limpo = EmailLogSanitizer::corpo($corpo);

        $this->assertStringNotContainsString($token, $limpo);
        $this->assertStringNotContainsString('abc123', $limpo);
        $this->assertStringNotContainsString('def456', $limpo);
        $this->assertStringContainsString('/admissao/[oculto]', $limpo);
        $this->assertStringContainsString('id=7', $limpo);
        $this->assertStringContainsString('Olá, acesse', $limpo);
        $this->assertStringContainsString('Prazo: 10/10.', $limpo);
    }

    public function test_sanitizador_omite_todo_o_corpo_das_notificacoes_de_autenticacao_do_filament(): void
    {
        $this->assertStringContainsString(
            'Conteúdo omitido',
            EmailLogSanitizer::corpo('<a href="https://x/reset">Redefinir</a>', 'Filament\\Auth\\Notifications\\ResetPassword'),
        );

        $this->assertSame('<p>Olá</p>', EmailLogSanitizer::corpo('<p>Olá</p>', 'App\\Notifications\\OutraNotificacao'));
    }

    public function test_comando_limpa_o_que_ja_estava_gravado_e_e_idempotente(): void
    {
        $logSensivel = EmailLog::create([
            'to' => ['ana@teste.com'], 'subject' => 'Bem-vindo ao Torre360',
            'body' => '<p>Senha: Abc12345</p>', 'sent_at' => now(),
        ]);
        $logResetLink = EmailLog::create([
            'to' => ['ana@teste.com'], 'subject' => 'Redefinir senha',
            'body' => '<a href="https://x/admin/password-reset/reset?token=segredo">Redefinir</a>', 'sent_at' => now(),
        ]);
        $logComum = EmailLog::create([
            'to' => ['ana@teste.com'], 'subject' => 'Aviso de reunião',
            'body' => '<p>Reunião dia 10.</p>', 'sent_at' => now(),
        ]);

        DB::table('failed_jobs')->insert([
            ['uuid' => 'a', 'connection' => 'database', 'queue' => 'default', 'payload' => '{"displayName":"App\\\\Notifications\\\\WelcomeUserMail","password":"Abc12345"}', 'exception' => 'x', 'failed_at' => now()],
            ['uuid' => 'b', 'connection' => 'database', 'queue' => 'default', 'payload' => '{"displayName":"App\\\\Jobs\\\\OutroJob"}', 'exception' => 'x', 'failed_at' => now()],
        ]);

        // Simulação não grava.
        Artisan::call('seguranca:higienizar-credenciais', ['--dry-run' => true]);
        $this->assertSame('<p>Senha: Abc12345</p>', $logSensivel->fresh()->body);
        $this->assertSame(2, DB::table('failed_jobs')->count());

        Artisan::call('seguranca:higienizar-credenciais');

        $this->assertStringContainsString('Conteúdo omitido', $logSensivel->fresh()->body);
        $this->assertStringNotContainsString('segredo', $logResetLink->fresh()->body);
        $this->assertSame('<p>Reunião dia 10.</p>', $logComum->fresh()->body);
        $this->assertSame(1, DB::table('failed_jobs')->count());
        $this->assertDatabaseHas('failed_jobs', ['uuid' => 'b']);

        // Segunda rodada: nada mais a alterar.
        Artisan::call('seguranca:higienizar-credenciais');
        $this->assertStringContainsString('alterados: 0', Artisan::output());
    }
}
