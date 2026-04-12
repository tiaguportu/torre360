<?php

namespace App\Services;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmService
{
    /**
     * Envia uma notificação Push para um token específico usando a API HTTP v1.
     */
    public function sendPush(string $token, string $title, string $body, array $data = [])
    {
        $credentialsPath = storage_path('app/firebase-credentials.json');

        if (! file_exists($credentialsPath)) {
            Log::warning('Arquivo de credenciais do Firebase não encontrado em: '.$credentialsPath);

            return false;
        }

        try {
            // Cria credenciais usando google/auth
            $credentials = new ServiceAccountCredentials(
                ['https://www.googleapis.com/auth/firebase.messaging'],
                $credentialsPath
            );

            // Tenta obter o token OAuth 2.0
            $authToken = $credentials->fetchAuthToken();

            if (! isset($authToken['access_token'])) {
                Log::error('Não foi possível obter o access_token do Firebase.');

                return false;
            }

            $accessToken = $authToken['access_token'];

            // Lendo o JSON para extrair o project_id (ou podemos colocar no config)
            $serviceAccount = json_decode(file_get_contents($credentialsPath), true);
            $projectId = $serviceAccount['project_id'];

            $fcmUrl = 'https://fcm.googleapis.com/v1/projects/'.$projectId.'/messages:send';

            // Na v1, os dados customizados (payload) devem ser strings.
            $stringData = [];
            foreach ($data as $key => $value) {
                // Ensure the value is a string as FCM v1 only accepts string values in 'data'
                $stringData[(string) $key] = is_string($value) ? $value : json_encode($value);
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$accessToken,
                'Content-Type' => 'application/json',
            ])->post($fcmUrl, [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => empty($stringData) ? null : $stringData,
                    'android' => [
                        'notification' => [
                            'sound' => 'default',
                        ],
                    ],
                ],
            ]);

            if (! $response->successful()) {
                Log::error('Erro ao enviar Push via FCM v1: '.$response->body());

                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Exceção ao enviar Push via FCM v1: '.$e->getMessage());

            return false;
        }
    }
}
