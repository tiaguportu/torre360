<?php

namespace Tests\Feature\Notifications;

use App\Models\CronogramaAula;
use App\Models\Matricula;
use App\Models\User;
use App\Notifications\DocumentosPendentesNotification;
use App\Notifications\FrequenciaPendenteNotification;
use App\Notifications\SystemNotification;
use App\Services\FcmService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

class NotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock do FcmService para evitar erro de credenciais JSON
        $fcmMock = Mockery::mock(FcmService::class);
        $fcmMock->shouldReceive('sendPush')
            ->andReturn(['success' => true, 'status' => 200]);

        $this->app->instance(FcmService::class, $fcmMock);
    }

    /** @test */
    public function it_can_send_system_notification_to_all_channels()
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'test@example.com',
            'fcm_token' => 'fake-token',
        ]);

        NotificationService::send($user, 'Teste de Notificação', [
            'body' => 'Esta é uma notificação do sistema.',
            'action_url' => '/admin',
            'type' => 'success',
        ]);

        Notification::assertSentTo(
            $user,
            SystemNotification::class,
            function ($notification, $channels) {
                return in_array('mail', $channels) &&
                       in_array('database', $channels) &&
                       in_array('App\Notifications\Channels\FcmChannel', $channels);
            }
        );
    }

    /** @test */
    public function it_sends_frequencia_pendente_notification_via_all_channels()
    {
        Notification::fake();

        $user = User::factory()->create(['fcm_token' => 'token-123']);
        $cronograma = CronogramaAula::factory()->create();

        $user->notify(new FrequenciaPendenteNotification($cronograma));

        Notification::assertSentTo(
            $user,
            FrequenciaPendenteNotification::class,
            function ($notification, $channels) {
                return in_array('mail', $channels) &&
                       in_array('database', $channels) &&
                       in_array('App\Notifications\Channels\FcmChannel', $channels);
            }
        );
    }

    /** @test */
    public function it_sends_documentos_pendentes_notification_via_all_channels()
    {
        Notification::fake();

        $user = User::factory()->create(['fcm_token' => 'token-456']);
        $matricula = Matricula::factory()->create();

        $user->notify(new DocumentosPendentesNotification($matricula));

        Notification::assertSentTo(
            $user,
            DocumentosPendentesNotification::class,
            function ($notification, $channels) {
                return in_array('mail', $channels) &&
                       in_array('database', $channels) &&
                       in_array('App\Notifications\Channels\FcmChannel', $channels);
            }
        );
    }
}
