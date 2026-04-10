<?php

namespace App\Notifications\Channels;

use App\Services\FcmService;
use Illuminate\Notifications\Notification;

class FcmChannel
{
    public function __construct(protected FcmService $fcmService)
    {
    }

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

        $this->fcmService->sendPush(
            $fcmToken,
            $data['title'],
            $data['body'],
            $data['data'] ?? []
        );
    }
}
