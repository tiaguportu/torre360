<?php

namespace App\Services\Canais;

use App\Contracts\CanalMensagem;
use App\Models\Pessoa;
use App\Services\FcmService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Envia push (FCM) para os usuários de app vinculados à Pessoa. Uma pessoa
 * pode ter mais de um usuário (raro); a mensagem vai para todos com token.
 */
class FcmCanal implements CanalMensagem
{
    public function __construct(private readonly FcmService $fcmService) {}

    public function chave(): string
    {
        return 'fcm';
    }

    public function rotulo(): string
    {
        return 'Notificação push (app)';
    }

    public function disponivelPara(Pessoa $pessoa): bool
    {
        return $this->tokensDaPessoa($pessoa)->isNotEmpty();
    }

    public function enviar(Pessoa $pessoa, string $assunto, string $corpo): bool
    {
        $tokens = $this->tokensDaPessoa($pessoa);

        if ($tokens->isEmpty()) {
            return false;
        }

        $texto = trim(Str::limit(strip_tags($corpo), 180));
        $enviouAlgum = false;

        foreach ($tokens as $token) {
            try {
                $resultado = $this->fcmService->sendPush($token, $assunto, $texto);
                $enviouAlgum = $enviouAlgum || ($resultado['success'] ?? false);
            } catch (\Throwable $e) {
                Log::error("FcmCanal: falha ao enviar push para pessoa {$pessoa->id}: ".$e->getMessage());
            }
        }

        return $enviouAlgum;
    }

    /**
     * @return Collection<int, string>
     */
    private function tokensDaPessoa(Pessoa $pessoa): Collection
    {
        return $pessoa->users()->ativos()->pluck('fcm_token')->filter()->unique()->values();
    }
}
