<?php

namespace App\Services;

class FcmService
{
    /**
     * Envia uma notificação Push para um token específico.
     */
    public function sendPush(string $token, string $title, string $body, array $data = [])
    {
        $fcmUrl = config('services.fcm.url', 'https://fcm.googleapis.com/fcm/send');
        $serverKey = config('services.fcm.key');

        if (! $serverKey) {
            \Illuminate\Support\Facades\Log::warning('FCM Server Key não configurada no .env (FCM_SERVER_KEY)');
            return false;
        }

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'key=' . $serverKey,
                'Content-Type' => 'application/json',
            ])->post($fcmUrl, [
                'to' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                    'sound' => 'default',
                ],
                'data' => $data,
                'priority' => 'high',
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Erro ao enviar Push via FCM: ' . $e->getMessage());
            return false;
        }
    }
}
