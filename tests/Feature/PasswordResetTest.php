<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\CustomRequestPasswordReset;
use App\Models\User;
use Filament\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_tela_de_solicitacao_de_reset_carrega_com_sucesso(): void
    {
        $response = $this->get('/admin/password-reset/request');

        $response->assertOk();
    }

    public function test_solicitacao_de_reset_envia_link_seguro_e_nao_altera_senha_imediatamente(): void
    {
        Notification::fake();

        $originalPassword = 'MinhaSenhaAntiga123!';

        $user = User::factory()->create([
            'email' => 'seguranca@torre360.com.br',
            'password' => Hash::make($originalPassword),
            'activated_at' => now(),
            'email_verified_at' => now(),
        ]);
        $user->assignRole('super_admin');

        Livewire::test(CustomRequestPasswordReset::class)
            ->fillForm([
                'email' => $user->email,
            ])
            ->call('request')
            ->assertHasNoFormErrors();

        // 1. A senha no banco DEVE permanecer inalterada até que o link seja acessado e o reset concluído
        $userFresh = $user->fresh();
        $this->assertTrue(
            Hash::check($originalPassword, $userFresh->password),
            'A senha do usuário não pode ser alterada imediatamente na solicitação de reset!'
        );

        // 2. A notificação padrão com token e link assinado temporário DEVE ser enviada
        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            function (ResetPasswordNotification $notification) {
                return ! empty($notification->url);
            }
        );
    }

    public function test_solicitacao_com_email_inexistente_nao_quebra(): void
    {
        Notification::fake();

        Livewire::test(CustomRequestPasswordReset::class)
            ->fillForm([
                'email' => 'email_inexistente_12345@dominio.com',
            ])
            ->call('request')
            ->assertHasNoFormErrors();

        Notification::assertNothingSent();
    }
}
