<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Envia uma notificação para um usuário específico.
     * Pode ser uma instância de Notification ou dados para uma SystemNotification.
     */
    public static function send(User $user, Notification|string $notification, array $data = []): void
    {
        try {
            if (is_string($notification)) {
                $title = $notification;
                $body = $data['body'] ?? '';
                $actionUrl = $data['action_url'] ?? null;
                $actionLabel = $data['action_label'] ?? 'Ver';
                $type = $data['type'] ?? 'info';

                $notification = new SystemNotification($title, $body, $actionUrl, $actionLabel, $type);
            }

            $user->notify($notification);
        } catch (\Throwable $e) {
            Log::error("Erro ao enviar notificação para o usuário {$user->id}: ".$e->getMessage());
        }
    }

    /**
     * Atalho para enviar uma notificação para múltiplos usuários.
     */
    public static function sendToMany($users, Notification|string $notification, array $data = []): void
    {
        foreach ($users as $user) {
            self::send($user, $notification, $data);
        }
    }
}
