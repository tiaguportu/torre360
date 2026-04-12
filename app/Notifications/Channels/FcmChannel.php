<?php

namespace App\Notifications\Channels;

use App\Models\Matricula;
use App\Services\FcmService;
use Illuminate\Notifications\Notification;

class FcmChannel
{
    public function __construct(protected FcmService $fcmService) {}

    public function send($notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toPush')) {
            return;
        }

        $fcmToken = $notifiable->fcm_token;

        if (! $fcmToken) {
            return;
        }

        $data = $notification->toPush($notifiable);

        $result = $this->fcmService->sendPush(
            $fcmToken,
            $data['title'],
            $data['body'],
            $data['data'] ?? []
        );

        // Registro de Atividade (Audit Log)
        if (isset($notification->matricula) && $notification->matricula instanceof Matricula) {
            $status = $result['success'] ? 'Sucesso' : 'Falha';
            activity()
                ->performedOn($notification->matricula)
                ->event('fcm_push_notification')
                ->withProperties([
                    'destinatario' => "{$notifiable->name} ({$notifiable->email})",
                    'token_final' => substr($fcmToken, -10), // Logar apenas o final por segurança/espaço
                    'result' => $result,
                    'origem' => get_class($notification),
                ])
                ->log("Envio de Push (Firebase) para {$notifiable->name}: {$status}");
        }
    }
}
